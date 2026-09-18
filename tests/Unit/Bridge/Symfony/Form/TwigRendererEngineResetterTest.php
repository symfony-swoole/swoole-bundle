<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Symfony\Form;

use Assert\InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;
use stdClass;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Form\TwigRendererEngineResetter;
use Symfony\Bridge\Twig\Form\TwigRendererEngine;
use Symfony\Component\Form\FormView;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Template;
use Twig\TemplateWrapper;

final class TwigRendererEngineResetterTest extends TestCase
{
    private const string THEME = 'form_layout.html.twig';

    /**
     * The write this is all about: the engine resolves the theme name once and stores the resolved
     * template back over it, so a second request finds a template bound to the first request's
     * Environment.
     */
    public function testResetGivesTheDefaultThemesBackAsNames(): void
    {
        self::skipOnTheBlockChainPath();

        $engine = $this->engineThatHasRenderedAForm();

        self::assertNotSame([self::THEME], self::defaultThemesOf($engine));

        (new TwigRendererEngineResetter())->reset($engine);

        self::assertSame([self::THEME], self::defaultThemesOf($engine));
    }

    /**
     * Assigned with `??=` and never cleared, so restoring the themes around it would not stop the next
     * coroutine rendering every block through the Environment this one used.
     */
    public function testResetPutsTheRememberedTemplateBackToUninitialized(): void
    {
        self::skipOnTheBlockChainPath();

        $engine = $this->engineThatHasRenderedAForm();
        $template = new ReflectionProperty(TwigRendererEngine::class, 'template');

        self::assertTrue($template->isInitialized($engine));

        (new TwigRendererEngineResetter())->reset($engine);

        self::assertFalse($template->isInitialized($engine));
    }

    /**
     * The same write on the block chain path: the chain composed from the default themes is built with
     * the engine's Environment and memoized for the life of the instance, and Symfony's own reset()
     * clears the per-view chains but not this one.
     */
    public function testResetForgetsTheDefaultBlockChain(): void
    {
        if (!self::usesBlockChains()) {
            self::markTestSkipped('The installed twig-bridge or Twig predates the block chain path.');
        }

        $engine = $this->engineThatHasRenderedAForm();

        self::assertNotNull(self::propertyOf($engine, 'defaultChain'));

        (new TwigRendererEngineResetter())->reset($engine);

        self::assertNull(self::propertyOf($engine, 'defaultChain'));
    }

    /**
     * The resetter replaces the one the pool would otherwise call, so everything Symfony's own reset()
     * clears has to keep being cleared.
     */
    public function testResetStillClearsWhatSymfonysOwnResetClears(): void
    {
        $engine = $this->engineThatHasRenderedAForm();

        (new TwigRendererEngineResetter())->reset($engine);

        self::assertSame([], self::propertyOf($engine, 'resources'));
        self::assertSame([], self::propertyOf($engine, 'themes'));
    }

    /**
     * The failure the resetter exists for, in the shape the pool produces it: one engine instance
     * serving two requests in turn, with a different Environment behind the twig proxy each time.
     * Without the reset in the middle, the second request renders every block through a template that
     * still belongs to the first request's Environment - which is the cross-coroutine write that shows
     * up as WebProfilerExtension::$stackLevel, Environment::$loadedTemplates and
     * ExtensionSet::$runtimeInitialized.
     */
    public function testAnEngineHandedOnAfterAResetRendersThroughTheNextEnvironment(): void
    {
        $first = $this->environment();
        $engine = new TwigRendererEngine([self::THEME], $first);
        $this->renderAForm($engine);

        self::assertSame($first, self::environmentTheEngineRendersThrough($engine));

        (new TwigRendererEngineResetter())->reset($engine);

        $second = $this->environment();
        (new ReflectionProperty(TwigRendererEngine::class, 'environment'))->setValue($engine, $second);
        $this->renderAForm($engine);

        self::assertSame($second, self::environmentTheEngineRendersThrough($engine));
    }

    public function testResetOfAnEngineThatNeverRenderedLeavesItsThemesAlone(): void
    {
        $engine = new TwigRendererEngine([self::THEME], $this->environment());

        (new TwigRendererEngineResetter())->reset($engine);

        self::assertSame([self::THEME], self::defaultThemesOf($engine));
    }

    public function testResetRejectsAnUnsupportedObject(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new TwigRendererEngineResetter())->reset(new stdClass());
    }

    public function testResetReusesItsReflectionAndClosureAcrossCalls(): void
    {
        $resetter = new TwigRendererEngineResetter();
        $property = new ReflectionProperty(TwigRendererEngineResetter::class, 'defaultThemesProperty');
        $closure = new ReflectionProperty(TwigRendererEngineResetter::class, 'templateUnsetter');
        $chain = new ReflectionProperty(TwigRendererEngineResetter::class, 'defaultChainProperty');

        $resetter->reset($this->engineThatHasRenderedAForm());
        $resolvedProperty = $property->getValue($resetter);
        $resolvedClosure = $closure->getValue($resetter);
        // false where the installed engine has no block chain path, which is remembered as well
        $resolvedChain = $chain->getValue($resetter);

        self::assertNotNull($resolvedProperty);
        self::assertNotNull($resolvedClosure);
        self::assertNotNull($resolvedChain);

        $resetter->reset($this->engineThatHasRenderedAForm());

        self::assertSame($resolvedProperty, $property->getValue($resetter));
        self::assertSame($resolvedClosure, $closure->getValue($resetter));
        self::assertSame($resolvedChain, $chain->getValue($resetter));
    }

    /**
     * Drives the engine the way a form render does - the root view resolving its default themes - so
     * the state under test is the state a real request leaves behind, not a hand-written stand-in.
     */
    private function engineThatHasRenderedAForm(): TwigRendererEngine
    {
        $engine = new TwigRendererEngine([self::THEME], $this->environment());
        $this->renderAForm($engine);

        return $engine;
    }

    private function renderAForm(TwigRendererEngine $engine): void
    {
        self::skipWhereTheEngineCannotRender();

        $view = new FormView();
        $view->vars[TwigRendererEngine::CACHE_KEY_VAR] = '_form';

        $engine->getResourceForBlockName($view, 'form_widget');
    }

    /**
     * The Environment every block of the default themes is rendered through, which is the whole
     * question: a template carries the Environment that compiled it, in Template::$env, and a block
     * chain the one it was built with, in BlockChain::$env.
     *
     * Asked of whichever road the installed versions take - the resetter has to be right on both, and
     * the lowest and the latest builds each take a different one.
     */
    private static function environmentTheEngineRendersThrough(TwigRendererEngine $engine): Environment
    {
        if (self::usesBlockChains()) {
            $chain = self::propertyOf($engine, 'defaultChain');
            self::assertIsObject($chain);
            $environment = (new ReflectionProperty($chain, 'env'))->getValue($chain);
        } else {
            $template = self::propertyOf($engine, 'template');
            self::assertInstanceOf(Template::class, $template);
            $environment = (new ReflectionProperty(Template::class, 'env'))->getValue($template);
        }

        self::assertInstanceOf(Environment::class, $environment);

        return $environment;
    }

    /**
     * The condition the engine itself decides by - Twig having BlockChain - together with a twig-bridge
     * new enough to have the property the path is kept in. Either missing, and the engine takes the
     * old road through `$defaultThemes` and `$template`.
     */
    private static function usesBlockChains(): bool
    {
        return class_exists('Twig\\BlockChain') && property_exists(TwigRendererEngine::class, 'defaultChain');
    }

    /**
     * Symfony 8.0 is past its end of life, and its last twig-bridge - 8.0.15 - cannot render a form on
     * Twig 3.29 or later. Twig 3.29 made `TemplateWrapper::unwrap()` require the Environment, the old
     * road still calls it with none, and 7.4.19 and 8.1.7 only get past it by taking the block chain
     * road, which 8.0 never got. Every render then dies in Symfony's own code on an ArgumentCountError,
     * before a resetter has anything to reset.
     *
     * So there is nothing to test on that pair, and nothing to fix here either: an application running
     * it cannot render the form at all. Asked of the installed code rather than of version numbers, so
     * it skips exactly where the engine would take the old road into an `unwrap()` it cannot call.
     */
    private static function skipWhereTheEngineCannotRender(): void
    {
        if (self::usesBlockChains()) {
            return;
        }

        if ((new ReflectionMethod(TemplateWrapper::class, 'unwrap'))->getNumberOfRequiredParameters() === 0) {
            return;
        }

        self::markTestSkipped(
            'The installed twig-bridge predates the block chain road and the installed Twig requires an '
            . 'argument to TemplateWrapper::unwrap(), so the engine cannot render a form at all.',
        );
    }

    private static function skipOnTheBlockChainPath(): void
    {
        if (!self::usesBlockChains()) {
            return;
        }

        self::markTestSkipped(
            'The installed versions render through block chains, which never write to $defaultThemes or '
            . '$template - see testResetForgetsTheDefaultBlockChain for the same guarantee on that path.',
        );
    }

    private function environment(): Environment
    {
        return new Environment(new ArrayLoader([
            self::THEME => '{% block form_widget %}widget{% endblock %}',
        ]));
    }

    /**
     * @return array<mixed>
     */
    private static function defaultThemesOf(TwigRendererEngine $engine): array
    {
        /** @var array<mixed> $defaultThemes */
        $defaultThemes = self::propertyOf($engine, 'defaultThemes');

        return $defaultThemes;
    }

    private static function propertyOf(TwigRendererEngine $engine, string $property): mixed
    {
        return (new ReflectionProperty(TwigRendererEngine::class, $property))->getValue($engine);
    }
}

<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Symfony\Form;

use Assert\InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use stdClass;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Form\TwigRendererEngineResetter;
use Symfony\Bridge\Twig\Form\TwigRendererEngine;
use Symfony\Component\Form\FormView;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Template;

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
        $engine = $this->engineThatHasRenderedAForm();
        $template = new ReflectionProperty(TwigRendererEngine::class, 'template');

        self::assertTrue($template->isInitialized($engine));

        (new TwigRendererEngineResetter())->reset($engine);

        self::assertFalse($template->isInitialized($engine));
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

        self::assertSame($first, self::environmentOfTheRememberedTemplate($engine));

        (new TwigRendererEngineResetter())->reset($engine);

        $second = $this->environment();
        (new ReflectionProperty(TwigRendererEngine::class, 'environment'))->setValue($engine, $second);
        $this->renderAForm($engine);

        self::assertSame($second, self::environmentOfTheRememberedTemplate($engine));
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

        $resetter->reset($this->engineThatHasRenderedAForm());
        $resolvedProperty = $property->getValue($resetter);
        $resolvedClosure = $closure->getValue($resetter);

        self::assertNotNull($resolvedProperty);
        self::assertNotNull($resolvedClosure);

        $resetter->reset($this->engineThatHasRenderedAForm());

        self::assertSame($resolvedProperty, $property->getValue($resetter));
        self::assertSame($resolvedClosure, $closure->getValue($resetter));
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
        $view = new FormView();
        $view->vars[TwigRendererEngine::CACHE_KEY_VAR] = '_form';

        $engine->getResourceForBlockName($view, 'form_widget');
    }

    /**
     * The template the engine renders every block through, and the Environment it is bound to - which
     * is the whole question: a template carries the Environment that compiled it, in Template::$env.
     */
    private static function environmentOfTheRememberedTemplate(TwigRendererEngine $engine): Environment
    {
        $template = (new ReflectionProperty(TwigRendererEngine::class, 'template'))->getValue($engine);
        self::assertInstanceOf(Template::class, $template);

        $environment = (new ReflectionProperty(Template::class, 'env'))->getValue($template);
        self::assertInstanceOf(Environment::class, $environment);

        return $environment;
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

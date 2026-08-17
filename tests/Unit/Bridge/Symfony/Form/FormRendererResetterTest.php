<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Symfony\Form;

use Assert\InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use RuntimeException;
use stdClass;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Form\FormRendererResetter;
use Symfony\Component\Form\FormRenderer;
use Symfony\Component\Form\FormRendererEngineInterface;
use Symfony\Component\Form\FormView;

final class FormRendererResetterTest extends TestCase
{
    public function testResetEmptiesEveryStackAHalfFinishedRenderLeftBehind(): void
    {
        $renderer = $this->rendererWith([
            'variableStack' => ['_registration_form' => [['name' => 'email']]],
            'blockNameHierarchyMap' => ['_registration_formwidget' => ['form_widget']],
            'hierarchyLevelMap' => ['_registration_formwidget' => 1],
        ]);

        (new FormRendererResetter())->reset($renderer);

        self::assertSame([], self::propertyOf($renderer, 'variableStack'));
        self::assertSame([], self::propertyOf($renderer, 'blockNameHierarchyMap'));
        self::assertSame([], self::propertyOf($renderer, 'hierarchyLevelMap'));
    }

    /**
     * Why the resetter exists at all: the pops that balance the stack are not in a finally, so a block
     * that throws leaves its scope behind - and the next render of a form with the same cache key
     * resumes from it instead of starting a fresh one.
     */
    public function testARenderThatThrewLeavesItsScopeOnTheStackUntilReset(): void
    {
        $engine = $this->createStub(FormRendererEngineInterface::class);
        $engine->method('getResourceForBlockName')->willReturn('form_start');
        $engine->method('renderBlock')->willThrowException(new RuntimeException('block blew up'));
        $renderer = new FormRenderer($engine);
        $view = new FormView();
        $view->vars[FormRenderer::CACHE_KEY_VAR] = '_registration_form';

        try {
            $renderer->renderBlock($view, 'form_start');
            self::fail('The engine was set up to throw.');
        } catch (RuntimeException) {
            // the point of the test is what the renderer is left holding, not the exception itself
        }

        self::assertArrayHasKey('_registration_form', self::propertyOf($renderer, 'variableStack'));

        (new FormRendererResetter())->reset($renderer);

        self::assertSame([], self::propertyOf($renderer, 'variableStack'));
    }

    public function testResetOfARendererThatFinishedItsRenderLeavesItEmpty(): void
    {
        $renderer = $this->rendererWith([]);

        (new FormRendererResetter())->reset($renderer);

        self::assertSame([], self::propertyOf($renderer, 'variableStack'));
    }

    public function testResetRejectsAnUnsupportedObject(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new FormRendererResetter())->reset(new stdClass());
    }

    public function testResetReusesTheSameReflectionPropertyInstancesAcrossCalls(): void
    {
        $resetter = new FormRendererResetter();
        $cache = new ReflectionProperty(FormRendererResetter::class, 'properties');

        self::assertSame([], $cache->getValue($resetter));

        $resetter->reset($this->rendererWith([]));
        $resolved = $cache->getValue($resetter);

        self::assertCount(3, $resolved);

        $resetter->reset($this->rendererWith([]));

        self::assertSame($resolved, $cache->getValue($resetter));
    }

    /**
     * @param array<string, mixed> $state
     */
    private function rendererWith(array $state): FormRenderer
    {
        $renderer = new FormRenderer($this->createStub(FormRendererEngineInterface::class));

        foreach ($state as $property => $value) {
            (new ReflectionProperty(FormRenderer::class, $property))->setValue($renderer, $value);
        }

        return $renderer;
    }

    private static function propertyOf(FormRenderer $renderer, string $property): mixed
    {
        return (new ReflectionProperty(FormRenderer::class, $property))->getValue($renderer);
    }
}

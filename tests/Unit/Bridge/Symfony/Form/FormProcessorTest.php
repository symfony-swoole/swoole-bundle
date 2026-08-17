<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Symfony\Form;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices\{
    ClassModificationProcessor,
    Proxifier,
};
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\ContainerConstants;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Form\FormProcessor;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Form\FormRendererResetter;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Form\TwigRendererEngineResetter;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Form\OtherRendererEngine;
use Symfony\Bridge\Twig\Form\TwigRendererEngine;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Form\FormRenderer;

#[CoversClass(FormProcessor::class)]
final class FormProcessorTest extends TestCase
{
    private const string RENDERER_ID = 'twig.form.renderer';
    private const string RENDERER_RESETTER_ID = 'swoole_bundle.form.renderer_resetter';
    private const string ENGINE_ID = 'twig.form.engine';
    private const string ENGINE_RESETTER_ID = 'swoole_bundle.form.twig_renderer_engine_resetter';

    public function testTheFormRendererIsPooledWithItsOwnResetter(): void
    {
        $container = $this->containerWithFormRenderer();

        $this->process($container);

        self::assertSame(
            [['resetter' => self::RENDERER_RESETTER_ID]],
            $container->getDefinition(self::RENDERER_ID)->getTag(ContainerConstants::TAG_STATEFUL_SERVICE),
        );
        self::assertSame(
            FormRendererResetter::class,
            $container->getDefinition(self::RENDERER_RESETTER_ID)->getClass(),
        );
    }

    /**
     * The renderer is registered by TwigBundle together with the form integration, and an application
     * with neither has nothing here to pool.
     */
    public function testAnApplicationWithoutFormRenderingIsLeftAlone(): void
    {
        $container = $this->newContainer();

        $this->process($container);

        self::assertFalse($container->hasDefinition(self::RENDERER_RESETTER_ID));
    }

    /**
     * Templates reach the renderer through Twig's runtime locator, which RuntimeLoaderPass keys by the
     * definition's class. Pooling must not change that class, or every form_widget() in the
     * application stops resolving.
     */
    public function testTheRendererKeepsItsClassAndItsRuntimeTag(): void
    {
        $container = $this->containerWithFormRenderer();

        $this->process($container);

        $definition = $container->getDefinition(self::RENDERER_ID);
        self::assertSame(FormRenderer::class, $definition->getClass());
        self::assertSame([[]], $definition->getTag('twig.runtime'));
    }

    /**
     * Compile processors run before StatefulServicesPass acts on the tags, and it is the tag - not a
     * proxifyService() call - that has to be there when it does.
     */
    public function testTheRendererIsNotProxifiedByTheProcessorItself(): void
    {
        $container = $this->containerWithFormRenderer();

        $this->process($container);

        self::assertFalse($container->has(self::RENDERER_ID . '.swoole_coop.wrapped'));
    }

    /**
     * The engine is pooled already through its own `kernel.reset` tag; what it gets here is a wider
     * reset than the one its class offers.
     */
    public function testTheTwigRendererEngineGetsTheWiderResetter(): void
    {
        $container = $this->containerWithFormRenderer();
        $container->register(self::ENGINE_ID, TwigRendererEngine::class);

        $this->process($container);

        self::assertSame(
            [['resetter' => self::ENGINE_RESETTER_ID]],
            $container->getDefinition(self::ENGINE_ID)->getTag(ContainerConstants::TAG_STATEFUL_SERVICE),
        );
        self::assertSame(
            TwigRendererEngineResetter::class,
            $container->getDefinition(self::ENGINE_RESETTER_ID)->getClass(),
        );
    }

    /**
     * The two properties the wider reset undoes are the Twig engine's own, so an application rendering
     * through an engine of its own keeps the resetter Symfony's tag already gave it.
     */
    public function testAnEngineOfTheApplicationsOwnIsLeftAlone(): void
    {
        $container = $this->containerWithFormRenderer();
        $container->register(self::ENGINE_ID, OtherRendererEngine::class);

        $this->process($container);

        self::assertSame(
            [],
            $container->getDefinition(self::ENGINE_ID)->getTag(ContainerConstants::TAG_STATEFUL_SERVICE),
        );
        self::assertFalse($container->hasDefinition(self::ENGINE_RESETTER_ID));
    }

    public function testTheResetterIsRegisteredOnlyOnce(): void
    {
        $container = $this->containerWithFormRenderer();
        $container->register(self::RENDERER_RESETTER_ID, FormRendererResetter::class)
            ->addTag('already.registered');

        $this->process($container);

        self::assertSame(
            [[]],
            $container->getDefinition(self::RENDERER_RESETTER_ID)->getTag('already.registered'),
        );
    }

    private function containerWithFormRenderer(): ContainerBuilder
    {
        $container = $this->newContainer();
        $container->register(self::RENDERER_ID, FormRenderer::class)
            ->addTag('twig.runtime');

        return $container;
    }

    private function process(ContainerBuilder $container): void
    {
        (new FormProcessor())->process(
            $container,
            new Proxifier($container, new ClassModificationProcessor($container)),
        );
    }

    private function newContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.cache_dir', sys_get_temp_dir());
        $container->setParameter(ContainerConstants::PARAM_COROUTINES_MAX_SVC_INSTANCES, 20);

        return $container;
    }
}

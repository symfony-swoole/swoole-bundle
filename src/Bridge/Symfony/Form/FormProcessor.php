<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Form;

use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices\CompileProcessor;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices\ServiceProxifier;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\ContainerConstants;
use Symfony\Bridge\Twig\Form\TwigRendererEngine;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * Gives every coroutine its own form renderer, and a way back for the one it hands in.
 *
 * A render walks down the form tree and back up again, and the renderer keeps its place in that walk
 * on itself - the variable scope of the view being rendered, the block hierarchy of the current
 * suffix, and how far down that hierarchy the search has got:
 *
 * ```php
 * $this->variableStack[$viewCacheKey][] = $variables;
 * $html = $this->engine->renderBlock($view, $resource, $blockName, $variables);
 * array_pop($this->variableStack[$viewCacheKey]);
 * ```
 *
 * Every block of every form goes through there, so one shared renderer puts two concurrent form pages
 * on the same stack - and the one that renders a block while the other is between its push and its
 * pop reads the other request's variables:
 *
 *   FiberViber\ConcurrencyException: Cross-coroutine access detected: [property_fetch_w]
 *   Symfony\Component\Form\FormRenderer::$variableStack is owned by coroutine #76 but accessed by
 *   coroutine #75
 *
 * Which is the loudest of the failures a form-rendering application sees under any real concurrency:
 * a burst of them per minute, and a 500 on every page with a form on it.
 *
 * The engine underneath it - the other half of the pair, and just as per-request - is pooled already,
 * because AbstractRendererEngine implements ResetInterface and Symfony therefore tags it
 * `kernel.reset`. The renderer has no reset() and no tag, so nothing picked it up, and pooling half a
 * pair fixes nothing. Saying so here is what makes it poolable at all, the same way
 * {@see \SwooleBundle\SwooleBundle\Bridge\Symfony\Twig\TwigProcessor} does for the twig profile.
 *
 * The renderer is reached as a Twig runtime rather than injected anywhere, which is what makes the
 * pool work without touching a single template: `RuntimeLoaderPass` keys the runtime locator by the
 * definition's class, the proxy definition keeps the class of the service it replaces, and
 * `getRuntime()` therefore still resolves `Symfony\Component\Form\FormRenderer` - to the proxy, which
 * resolves per coroutine. Its resetter is not optional - see {@see FormRendererResetter} for what a
 * half-finished render leaves behind.
 *
 * The engine then needs the other half of the same treatment: being pooled is not enough for it,
 * because the reset it comes with leaves behind the resolved form theme - and with it the Environment
 * that resolved it - for the next coroutine to render every block through. That is where the profiler
 * extension's $stackLevel kept being written across coroutines long after the extensions themselves
 * stopped being shared. See {@see TwigRendererEngineResetter}.
 */
final class FormProcessor implements CompileProcessor
{
    private const string RENDERER_ID = 'twig.form.renderer';
    private const string RENDERER_RESETTER_ID = 'swoole_bundle.form.renderer_resetter';
    private const string ENGINE_ID = 'twig.form.engine';
    private const string ENGINE_RESETTER_ID = 'swoole_bundle.form.twig_renderer_engine_resetter';

    public function process(ContainerBuilder $container, ServiceProxifier $proxifier): void
    {
        $this->poolRenderer($container);
        $this->replaceEngineResetter($container);
    }

    private function poolRenderer(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(self::RENDERER_ID)) {
            return;
        }

        $this->registerResetter($container, self::RENDERER_RESETTER_ID, FormRendererResetter::class);

        // Tagging rather than calling proxifyService(): the tag is what StatefulServicesPass acts on
        // once every compile processor has run, and doing both is refused outright by the Proxifier.
        $container->findDefinition(self::RENDERER_ID)
            ->addTag(ContainerConstants::TAG_STATEFUL_SERVICE, [
                'resetter' => self::RENDERER_RESETTER_ID,
            ]);
    }

    /**
     * The engine is already pooled without any help from here - AbstractRendererEngine implements
     * ResetInterface, so Symfony tags it `kernel.reset` and StatefulServicesPass picks it up with a
     * resetter that calls reset(). What it needs is a wider reset than the one that class offers, for
     * the two properties reset() leaves alone; {@see TwigRendererEngineResetter} calls it and then
     * undoes those as well.
     *
     * Only for the Twig engine, since the properties in question are its own. An application rendering
     * forms through an engine of its own keeps the resetter Symfony's tag already gave it.
     */
    private function replaceEngineResetter(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(self::ENGINE_ID)) {
            return;
        }

        $engineDefinition = $container->findDefinition(self::ENGINE_ID);
        $engineClass = $engineDefinition->getClass();

        if ($engineClass === null || !is_a($engineClass, TwigRendererEngine::class, true)) {
            return;
        }

        $this->registerResetter($container, self::ENGINE_RESETTER_ID, TwigRendererEngineResetter::class);

        $engineDefinition->addTag(ContainerConstants::TAG_STATEFUL_SERVICE, [
            'resetter' => self::ENGINE_RESETTER_ID,
        ]);
    }

    /**
     * @param class-string $class
     */
    private function registerResetter(ContainerBuilder $container, string $serviceId, string $class): void
    {
        if ($container->hasDefinition($serviceId)) {
            return;
        }

        $resetterDef = new Definition($class);
        $resetterDef->setPublic(false);
        $container->setDefinition($serviceId, $resetterDef);
    }
}

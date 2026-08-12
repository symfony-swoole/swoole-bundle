<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Messenger;

use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices\CompileProcessor;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices\ServiceProxifier;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\ContainerConstants;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\SimpleResetter;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\Messenger\TraceableMessageBus;

/**
 * Pools the debug wrapper Symfony puts around every message bus when the profiler is on.
 *
 * MessengerPass decorates each bus with a TraceableMessageBus and hands that same instance to
 * `data_collector.messenger` through a registerBus() call. The collector is pooled - every data
 * collector is - but the bus behind it is not, because TraceableMessageBus does not implement
 * ResetInterface and so never picks up the `kernel.reset` tag that autoconfiguration would have
 * given it. Nothing else about it says "stateful" either, so StatefulServicesPass has no reason to
 * look at it, and one shared instance ends up behind every pooled collector in the worker.
 *
 * It is emphatically stateful: dispatch() appends to $dispatchedMessages and reset() empties it. The
 * collector's own reset() calls through to it, so the write happens on the way out of every single
 * request, and two requests tearing down at once write the same property from two coroutines:
 *
 *   FiberViber\ConcurrencyException: Cross-coroutine access detected: [property_write]
 *   Symfony\Component\Messenger\TraceableMessageBus::$dispatchedMessages is owned by coroutine #460
 *   but accessed by coroutine #462
 *
 * That surfaces as a 500 during teardown of an otherwise healthy request, which for an SPA reading
 * it as a failed API call looks like being logged out.
 *
 * Pooling the wrapper gives each coroutine its own, so the collector - which now holds the pool's
 * proxy - collects and resets the messages belonging to the request it is collecting. The bus this
 * one decorates stays shared, unlike the traceable event dispatcher's inner dispatcher: a
 * TraceableMessageBus only delegates dispatch() and registers nothing on what it wraps.
 *
 * Debug-only by construction: MessengerPass builds these decorators only when
 * `data_collector.messenger` exists.
 */
final class MessengerProcessor implements CompileProcessor
{
    private const string TRACEABLE_BUS_RESETTER_ID = 'swoole_bundle.messenger.traceable_bus_resetter';

    public function process(ContainerBuilder $container, ServiceProxifier $proxifier): void
    {
        $traceableBusIds = $this->traceableBusIds($container);

        if ($traceableBusIds === []) {
            return;
        }

        // TraceableMessageBus::reset() exists but arrives through no interface the pool recognises, so
        // the resetter is spelled out rather than left to the ResetInterface fallback. Without one the
        // pooled instance would be recycled with the previous request's messages still on it, and only
        // the collector resetting it through the proxy would clear them - true today, and not something
        // this needs to depend on.
        if (!$container->hasDefinition(self::TRACEABLE_BUS_RESETTER_ID)) {
            $resetterDef = new Definition(SimpleResetter::class);
            $resetterDef->setArgument(0, 'reset');
            $resetterDef->setPublic(false);
            $container->setDefinition(self::TRACEABLE_BUS_RESETTER_ID, $resetterDef);
        }

        foreach ($traceableBusIds as $busId) {
            // Tagging rather than calling proxifyService(): the tag is what StatefulServicesPass acts on
            // once every compile processor has run, and doing both is refused outright by the Proxifier.
            $container->findDefinition($busId)
                ->addTag(ContainerConstants::TAG_STATEFUL_SERVICE, [
                    'resetter' => self::TRACEABLE_BUS_RESETTER_ID,
                ]);
        }
    }

    /**
     * Found by class rather than by the `messenger.bus` tag or the `debug.traced.` id prefix, because by
     * the time this runs Symfony's DecoratorServicePass has already swapped the decorator into the
     * decorated service's id and renamed the real bus to `<id>.inner`. What is left is a definition
     * under the bus's own id whose class is the wrapper - which is exactly the thing to pool, whatever
     * it ended up being called.
     *
     * @return list<string>
     */
    private function traceableBusIds(ContainerBuilder $container): array
    {
        $busIds = [];

        foreach ($container->getDefinitions() as $serviceId => $definition) {
            if ($definition->getClass() !== TraceableMessageBus::class) {
                continue;
            }

            $busIds[] = $serviceId;
        }

        return $busIds;
    }
}

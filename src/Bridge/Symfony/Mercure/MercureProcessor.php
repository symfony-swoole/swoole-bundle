<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Mercure;

use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices\CompileProcessor;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices\ServiceProxifier;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\ContainerConstants;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Mercure\Debug\TraceableHub;

/**
 * Gives every coroutine its own traced Mercure hub, so two publishes cannot write the same trace.
 *
 * When the profiler is on, MercureBundle decorates every hub with a TraceableHub, and each publish appends
 * what it sent to one array on the decorator:
 *
 * ```php
 * // publish()
 * $this->messages[] = ['object' => $update, 'duration' => ..., 'memory' => ...];
 * ```
 *
 * Nothing else about it looks stateful - it wraps a hub and times a call - so it is left shared, and a
 * worker that publishes from more than one coroutine writes that array from all of them. The first two
 * publishes to overlap are stopped by fiber viber:
 *
 *   Cross-coroutine access detected: [property_fetch_w] Symfony\Component\Mercure\Debug\TraceableHub::$messages
 *   is owned by coroutine #2 but accessed by coroutine #3
 *
 * That is not confined to web requests, and is rather worse outside them. A messenger consumer running its
 * handlers on coroutines publishes from each of them, and outside a request nothing ever calls reset(), so
 * the array also grows for the life of the worker - every update it ever published, kept for a profiler
 * panel no console command has. An exception raised from the publish drags that array along with it, and
 * dumping it for a log line is enough to exhaust a worker's memory.
 *
 * Pooled, each coroutine has its own, and the pool hands it back through reset(). TraceableHub implements
 * ResetInterface, which is StatefulServicesPass's documented fallback for a pooled service arriving without
 * a resetter, so none is named here. The stopwatch it times each publish with is already pooled
 * (`debug.stopwatch`), and the hub it decorates keeps nothing between calls and stays shared.
 *
 * Matched on the class, like the traced http client: once DecoratorServicePass has resolved the decoration,
 * neither the id the wrapper ends up under nor the `mercure.hub` tag it may have inherited is a dependable
 * way to tell it from the hub it wraps - and an application can name as many hubs as it likes.
 *
 * Debug-only by construction: MercureBundle registers the decorator only when its profiler integration is on.
 *
 * @see \SwooleBundle\SwooleBundle\Bridge\Symfony\HttpClient\HttpClientProcessor for the same shape
 */
final class MercureProcessor implements CompileProcessor
{
    public function process(ContainerBuilder $container, ServiceProxifier $proxifier): void
    {
        foreach ($container->getDefinitions() as $definition) {
            // Compared as a string and never autoloaded, for the reason HttpClientProcessor gives: this runs
            // over every definition in the container, and an application without symfony/mercure installed
            // has plenty of definitions naming classes it cannot load. TraceableHub is final, so there is no
            // subclass the exact comparison could miss.
            if ($definition->getClass() !== TraceableHub::class) {
                continue;
            }

            if ($definition->hasTag(ContainerConstants::TAG_STATEFUL_SERVICE)) {
                continue;
            }

            $definition->addTag(ContainerConstants::TAG_STATEFUL_SERVICE);
        }
    }
}

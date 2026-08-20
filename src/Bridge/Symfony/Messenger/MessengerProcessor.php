<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Messenger;

use ReflectionClass;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices\CompileProcessor;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices\ServiceProxifier;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\ContainerConstants;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\SimpleResetter;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\Messenger\TraceableMessageBus;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransportFactory;
use Symfony\Component\Messenger\Transport\Sync\SyncTransportFactory;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Throwable;

/**
 * Gives the coroutine-unsafe parts of messenger an instance per coroutine.
 *
 * Two of them, unrelated to each other beyond both being services messenger shares where it assumed a
 * process does one thing at a time: the transports, and the debug wrapper around every message bus.
 *
 * ---
 *
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
 * request, and two requests tearing down at once write the same property from two coroutines.
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

    /**
     * Transports there is no point giving anyone an instance of their own, or harm in doing so.
     *
     * The sync transport hands the message straight to the bus and keeps nothing between calls. The
     * in-memory one is the opposite: keeping what was sent is the whole of what it is for, and a test
     * that reads it back would find its own coroutine's instance rather than the one the code under
     * test sent through.
     */
    private const array TRANSPORT_FACTORIES_TO_LEAVE_SHARED = [
        SyncTransportFactory::class,
        InMemoryTransportFactory::class,
    ];

    public function process(ContainerBuilder $container, ServiceProxifier $proxifier): void
    {
        $this->poolTransports($container);

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
     * Gives every consumer its own transport.
     *
     * A transport is a shared service that keeps per-receive state on itself, which held while a process
     * ran one consumer and stops holding the moment a task worker group runs several. DoctrineTransport
     * memoizes the receiver it hands out, so all four consumers of a group poll through one
     * DoctrineReceiver and one Connection, and the bookkeeping on those is written by whichever of them
     * polled last.
     *
     * DoctrineReceiver::$retryingSafetyCounter is the clearest of them, because it exists for exactly
     * the situation it is then wrong about: it counts consecutive deadlocks so that a run of them
     * becomes an error rather than a silent stall, and its own comment gives "concurrent consumers" as
     * the reason there would be any. Shared, a successful poll by one consumer resets the count another
     * was accumulating, and three deadlocks spread across three consumers trip a limit meant for three
     * in a row on one - so the net both fires when nothing is stuck and fails to when something is.
     *
     * The queue underneath is untouched by this and is built to be shared - the doctrine transport
     * reads with SELECT ... FOR UPDATE SKIP LOCKED, so a row still goes to exactly one consumer.
     *
     * What stops the pool doing this on its own is the class. FrameworkExtension builds transports with
     * `new Definition(TransportInterface::class)` behind a factory, and Proxifier skips a definition
     * whose class is an interface - rightly, because a proxy generated from that type would implement
     * TransportInterface and nothing else, while a real transport is a good deal more:
     * DoctrineTransport is also SetupableTransportInterface, MessageCountAwareInterface,
     * ListableReceiverInterface and KeepaliveReceiverInterface, and messenger finds each of those with
     * an instanceof. Pooled from the declared type, `messenger:setup-transports` skips the transport
     * without a word and `messenger:stats` cannot count it.
     *
     * So the class is worked out first and written onto the definition, and the tag follows.
     */
    private function poolTransports(ContainerBuilder $container): void
    {
        $factoryClasses = $this->transportFactoryClasses($container);

        if ($factoryClasses === []) {
            return;
        }

        foreach (array_keys($container->findTaggedServiceIds('messenger.receiver')) as $transportId) {
            $definition = $container->findDefinition($transportId);

            // Anything else has been given a class by whoever defined it, and is either already
            // concrete - in which case the pool needs nothing from here - or something this has no
            // business rewriting.
            if ($definition->getClass() !== TransportInterface::class) {
                continue;
            }

            $transportClass = $this->transportClassOf($container, $definition, $factoryClasses);

            if ($transportClass === null) {
                continue;
            }

            $definition->setClass($transportClass);
            // Tagging rather than proxifying here: the tag is what StatefulServicesPass acts on once
            // every compile processor has run, and doing both is refused outright by the Proxifier.
            //
            // No resetter. A transport is not carrying a request's worth of state to be cleared between
            // uses - what it keeps is one consumer's view of one queue, and the point is that the next
            // consumer has its own rather than a cleaned copy of somebody else's.
            $definition->addTag(ContainerConstants::TAG_STATEFUL_SERVICE);
        }
    }

    /**
     * The concrete class a transport definition will turn out to be, or null if it cannot be known.
     *
     * Two questions, and the answer to either can be no. Which factory builds this DSN is asked of the
     * factories themselves, in priority order, rather than guessed from the scheme - a prefix table
     * here would go stale against the bridges and would never know about an application's own factory.
     * What that factory builds is then read off its name: XTransportFactory builds XTransport, beside
     * it. That is a convention rather than a contract, which is why it is checked rather than trusted -
     * the class has to exist, to be a TransportInterface, and to be something the proxy generator can
     * extend.
     *
     * @param list<class-string> $factoryClasses
     */
    private function transportClassOf(
        ContainerBuilder $container,
        Definition $definition,
        array $factoryClasses,
    ): ?string {
        $arguments = $definition->getArguments();
        $dsn = $arguments[0] ?? null;

        if (!is_string($dsn) || $this->dependsOnEnvironment($container, $dsn)) {
            return null;
        }

        $options = is_array($arguments[1] ?? null) ? $arguments[1] : [];
        $factoryClass = $this->factoryFor($factoryClasses, $dsn, $options);

        if ($factoryClass === null || in_array($factoryClass, self::TRANSPORT_FACTORIES_TO_LEAVE_SHARED, true)) {
            return null;
        }

        if (!str_ends_with($factoryClass, 'Factory')) {
            return null;
        }

        $transportClass = substr($factoryClass, 0, -strlen('Factory'));

        if (!class_exists($transportClass) || !is_a($transportClass, TransportInterface::class, true)) {
            return null;
        }

        $reflection = new ReflectionClass($transportClass);

        // What the proxy generator needs of it. A final or read-only transport cannot be extended, and
        // an abstract one is not what a factory returns - none of the three is worth failing the whole
        // compile over, so the transport simply stays shared.
        if ($reflection->isFinal() || $reflection->isAbstract() || $reflection->isReadOnly()) {
            return null;
        }

        return $transportClass;
    }

    /**
     * Asks each factory whether it handles this DSN, in the order the composite factory would.
     *
     * supports() is a string test over the DSN in every implementation there is - it has to be, since
     * the composite calls it on all of them before anything is built - so it is safe to ask here,
     * where there is nothing to construct a factory with. The instance is made without its constructor
     * for that reason, and a factory that turns out to need one is skipped rather than allowed to take
     * the compile down with it.
     *
     * @param list<class-string> $factoryClasses
     * @param array<string, mixed> $options
     * @return class-string|null
     */
    private function factoryFor(array $factoryClasses, string $dsn, array $options): ?string
    {
        foreach ($factoryClasses as $factoryClass) {
            try {
                $factory = (new ReflectionClass($factoryClass))->newInstanceWithoutConstructor();

                if (!$factory instanceof TransportFactoryInterface || !$factory->supports($dsn, $options)) {
                    continue;
                }
            } catch (Throwable) {
                continue;
            }

            return $factoryClass;
        }

        return null;
    }

    /**
     * The transport factories, in the order the composite one consults them.
     *
     * @return list<class-string>
     */
    private function transportFactoryClasses(ContainerBuilder $container): array
    {
        $byPriority = [];

        foreach ($container->findTaggedServiceIds('messenger.transport_factory') as $serviceId => $tags) {
            $class = $container->findDefinition($serviceId)->getClass();

            if ($class === null || !class_exists($class)) {
                continue;
            }

            /** @var int $priority */
            $priority = $tags[0]['priority'] ?? 0;
            $byPriority[$priority][] = $class;
        }

        krsort($byPriority);

        return array_merge(...array_values($byPriority));
    }

    /**
     * Whether the value is one only the running process can know.
     *
     * An env var reaches a compiler pass as a placeholder, so a DSN built from one says nothing about
     * which transport it will be. Resolving it here would answer the question with the environment the
     * container happened to be compiled in, which is the one thing a placeholder exists to avoid - so
     * the transport stays shared instead, and an application that wants it pooled has to spell the DSN
     * out.
     */
    private function dependsOnEnvironment(ContainerBuilder $container, string $value): bool
    {
        $usedEnvs = [];
        $container->resolveEnvPlaceholders($value, null, $usedEnvs);

        return $usedEnvs !== [];
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

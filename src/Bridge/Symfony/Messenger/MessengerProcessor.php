<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Messenger;

use ReflectionClass;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices\CompileProcessor;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices\ServiceProxifier;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServicesPass;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\ContainerConstants;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\SimpleResetter;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\Messenger\TraceableMessageBus;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransportFactory;
use Symfony\Component\Messenger\Transport\Sync\SyncTransportFactory;
use Symfony\Component\Messenger\Transport\TransportInterface;

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
     * The sync transport hands the message straight to the bus and keeps nothing between calls, and
     * this bundle's own task transport does the same through Server::task(). The in-memory one is the
     * opposite: keeping what was sent is the whole of what it is for, and a test that reads it back
     * would find its own coroutine's instance rather than the one the code under test sent through.
     */
    private const array TRANSPORT_FACTORIES_TO_LEAVE_SHARED = [
        SyncTransportFactory::class,
        InMemoryTransportFactory::class,
        SwooleServerTaskTransportFactory::class,
    ];

    /**
     * The method a transport factory builds transports with, and the suffix its name ends in.
     */
    private const string TRANSPORT_FACTORY_METHOD = 'createTransport';

    private const string TRANSPORT_FACTORY_SUFFIX = 'Factory';

    public function process(ContainerBuilder $container, ServiceProxifier $proxifier): void
    {
        $this->poolTransportFactories($container);

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
     * Gives every coroutine a transport of its own, by pooling what builds them.
     *
     * A transport keeps per-receive state on itself, which held while a process ran one consumer and
     * stops holding the moment a task worker group runs several. DoctrineTransport memoizes the
     * receiver it hands out, so consumers sharing one poll through a single DoctrineReceiver and
     * Connection, and the bookkeeping on those is written by whichever of them polled last.
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
     * **The factory is pooled, not the transport.** A transport service cannot be pooled directly:
     * FrameworkExtension builds it with `new Definition(TransportInterface::class)` behind a factory,
     * and a pool proxy generated from an interface would implement TransportInterface and nothing else,
     * while a real transport is a good deal more - DoctrineTransport is also
     * SetupableTransportInterface, MessageCountAwareInterface, ListableReceiverInterface and
     * KeepaliveReceiverInterface, each of which messenger finds with an instanceof. Working the
     * concrete class out from the DSN instead is what this used to do, and it is not safe: an
     * application that decorates `messenger.transport_factory` - which is a service like any other -
     * gets back a transport of the decorator's choosing, while the tagged factories go on answering the
     * DSN exactly as before, so the class written onto the definition is one the instance is not.
     *
     * A factory has none of those problems. Its class is a fact rather than an inference, and
     * {@see ContainerConstants::TAG_UNMANAGED_FACTORY} exists for precisely this shape: the tagged
     * service is wrapped so that the named method hands back a pool proxy instead of the object,
     * backed by a pool that calls the real method with the same arguments once per coroutine. Whatever
     * is built on top of it - a decorator chain, one layer or five - is built once and stays shared,
     * which is right, because a proxy resolves per coroutine on every call through it.
     *
     * What it builds is read off its name: XTransportFactory builds XTransport, beside it. A
     * convention rather than a contract, so it is checked rather than trusted, and a factory it does
     * not fit is left alone with a line in the build log saying so.
     */
    private function poolTransportFactories(ContainerBuilder $container): void
    {
        // The pass this processor runs under, and only ever used to name the lines below: the compiler
        // prefixes each with the class of the pass a message came from, and MessengerProcessor cannot
        // be that pass itself - CompileProcessor and CompilerPassInterface both declare process(), with
        // different signatures. A fresh instance is enough, since the class is all that is read off it.
        $log = new StatefulServicesPass();

        foreach (array_keys($container->findTaggedServiceIds('messenger.transport_factory')) as $factoryId) {
            $definition = $container->findDefinition($factoryId);
            $verdict = $this->poolingVerdictFor($definition);

            if ($verdict->transportClass === null) {
                if ($verdict->leftSharedBecause !== null) {
                    $container->log($log, sprintf(
                        'Transport factory "%s" is left shared, because %s. The transports it builds '
                        . 'are shared with it, so consumers running concurrently in one worker will '
                        . 'poll through one of them, and what each keeps about its own progress '
                        . 'through the queue is then written by whichever polled last.',
                        $factoryId,
                        $verdict->leftSharedBecause,
                    ));
                }

                continue;
            }

            // No resetter. A transport is not carrying a request's worth of state to be cleared between
            // uses - what it keeps is one consumer's view of one queue, and the point is that the next
            // consumer has its own rather than a cleaned copy of somebody else's.
            $definition->addTag(ContainerConstants::TAG_UNMANAGED_FACTORY, [
                'factoryMethod' => self::TRANSPORT_FACTORY_METHOD,
                'returnType' => $verdict->transportClass,
            ]);
        }
    }

    /**
     * Whether a transport factory can be pooled, as what, and when not, why not.
     *
     * Says why on the way out, for the reasons a developer could act on. The one that is nobody's
     * problem stays quiet: a sync or in-memory transport is left shared on purpose, and a reason given
     * for it would be noise in front of the ones that are not.
     */
    private function poolingVerdictFor(Definition $definition): TransportPoolingVerdict
    {
        $factoryClass = $definition->getClass();

        if ($factoryClass === null || !class_exists($factoryClass)) {
            return TransportPoolingVerdict::leftShared('its service definition names no class to read');
        }

        if (in_array($factoryClass, self::TRANSPORT_FACTORIES_TO_LEAVE_SHARED, true)) {
            return TransportPoolingVerdict::sharedOnPurpose();
        }

        if (!str_ends_with($factoryClass, self::TRANSPORT_FACTORY_SUFFIX)) {
            return TransportPoolingVerdict::leftShared(sprintf(
                'its name does not end in "%s", so there is no telling what it builds',
                self::TRANSPORT_FACTORY_SUFFIX,
            ));
        }

        $transportClass = mb_substr($factoryClass, 0, -mb_strlen(self::TRANSPORT_FACTORY_SUFFIX));

        if (!class_exists($transportClass) || !is_a($transportClass, TransportInterface::class, true)) {
            return TransportPoolingVerdict::leftShared(sprintf(
                'there is no transport class "%s" beside it to say what it builds - name the factory '
                . 'after its transport, or tag it "%s" with the returnType spelled out. Nothing to do '
                . 'if it builds no transport of its own and only returns what another factory built, '
                . 'since that one has a pool of its own',
                $transportClass,
                ContainerConstants::TAG_UNMANAGED_FACTORY,
            ));
        }

        $reflection = new ReflectionClass($transportClass);

        // What the proxy generator needs of the transport it will stand in for. None of the three is
        // worth failing the whole compile over, so the factory simply stays shared.
        if ($reflection->isFinal() || $reflection->isAbstract() || $reflection->isReadOnly()) {
            return TransportPoolingVerdict::leftShared(sprintf(
                'the transport it builds, "%s", cannot be extended to stand in for',
                $transportClass,
            ));
        }

        // Wrapping a factory means generating a proxy that extends it. A final one is dealt with - the
        // modification processor un-finals it - but a read-only class cannot be extended at all, and
        // the Proxifier refuses it outright rather than producing something broken.
        //
        // Asked last, and about the factory rather than the transport, because a factory that names no
        // transport of its own has already been answered above with something more useful to read. A
        // dispatching factory - one that picks another tagged factory by DSN and returns what that one
        // built - is read-only as often as not, and reporting it here would be wrong twice over: it
        // builds no transport to share, and the factory it delegates to has a pool of its own.
        $factoryReflection = new ReflectionClass($factoryClass);

        if ($factoryReflection->isReadOnly()) {
            return TransportPoolingVerdict::leftShared(
                'it is a read-only class, which cannot be wrapped to hand out pooled transports',
            );
        }

        return TransportPoolingVerdict::pooledAs($transportClass);
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

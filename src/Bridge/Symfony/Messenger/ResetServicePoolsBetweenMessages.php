<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Messenger;

use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\ServicePool\ServicePoolContainer;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;

/**
 * Runs the pool reset cycle before each message a messenger worker handles, and as soon as one fails.
 *
 * Every other unit of work in this bundle ends with its coroutine, and CoWrapper::defer() hands the
 * pooled instances back from there - an http request through ContextReleasingHttpKernelRequestHandler,
 * a swoole task through ContextReleasingTransportHandler. A messenger worker is the exception: whether
 * it runs as a task worker command group, where CommandGroupRunner wraps the whole of
 * messenger:consume in one CoWrapper::go(), or as a plain console command with no coroutine at all,
 * the release comes once when the command exits and never between messages. Nothing resets the pools
 * for the entire life of the worker.
 *
 * What that costs is a closed EntityManager. One failed message closes it, the stability check that
 * would have dropped it never runs, and every message after that dies on "The EntityManager is closed."
 * until someone restarts the worker.
 *
 * A failed message needs the cycle before the worker is done with it, not only before the next one. The
 * worker sends it for retry - or to the failure transport - while still inside the failure, and with the
 * Doctrine transport that send runs on the same pooled connection the handler just left broken: a
 * connection a deadlock left believing it is inside a transaction the server has forgotten fails the send
 * too, and the consumer dies with the message dead-lettered. So a failure drops what went bad first
 * (ConnectionStabilityChecker), ahead of Symfony's retry listener at priority 100 - and only that: the
 * resetters wait for the next message, since the failure listeners, logging included, still need the
 * failed message's state.
 *
 * Worker events are the precise boundary here and need no environment test to stay that way: they are
 * emitted only by messenger:consume. The http path never emits them, and neither does the swoole task
 * transport, which dispatches straight to the bus without a Worker - so this cannot fire in the two
 * contexts that already release for themselves.
 *
 * Registered whenever symfony/messenger is installed; harmless when nothing is pooled, since the cycle
 * skips every entry that has no instance assigned.
 */
final readonly class ResetServicePoolsBetweenMessages implements EventSubscriberInterface
{
    public function __construct(
        private ServicePoolContainer $servicePoolContainer,
    ) {}

    /**
     * @return array<string, array{string, int}>
     */
    public static function getSubscribedEvents(): array
    {
        // Ahead of the other listeners on this event, so that whatever they reach for is the instance
        // this message is going to use rather than the one the previous message left behind.
        return [
            WorkerMessageReceivedEvent::class => ['onMessageReceived', 1024],
            // Ahead of SendFailedMessageForRetryListener (100), which sends on the connection it would drop.
            WorkerMessageFailedEvent::class => ['onMessageFailed', 200],
        ];
    }

    public function onMessageReceived(WorkerMessageReceivedEvent $event): void
    {
        $this->servicePoolContainer->resetInCoroutine();
    }

    public function onMessageFailed(WorkerMessageFailedEvent $event): void
    {
        $this->servicePoolContainer->discardUnstableInCoroutine();
    }
}

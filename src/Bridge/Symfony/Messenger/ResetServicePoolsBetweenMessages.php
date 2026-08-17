<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Messenger;

use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\ServicePool\ServicePoolContainer;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;

/**
 * Runs the pool reset cycle before each message a messenger worker handles.
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
        return [WorkerMessageReceivedEvent::class => ['onMessageReceived', 1024]];
    }

    public function onMessageReceived(WorkerMessageReceivedEvent $event): void
    {
        $this->servicePoolContainer->resetInCoroutine();
    }
}

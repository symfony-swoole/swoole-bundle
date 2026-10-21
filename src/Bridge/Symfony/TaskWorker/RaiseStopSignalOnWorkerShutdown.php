<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\TaskWorker;

use SwooleBundle\SwooleBundle\Bridge\Symfony\Event\WorkerExitedEvent;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Event\WorkerStoppedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * EXPERIMENTAL. With coroutines off, raises the shared stop flag from whichever worker hears the server
 * is stopping - which in practice means the http worker, because the task worker cannot.
 *
 * A command running with coroutines off blocks its task worker's onWorkerStart and never returns to the
 * worker's event loop, and lifecycle callbacks are delivered through that loop. So the task worker fires
 * neither onWorkerExit nor onWorkerStop - it hears nothing at all - and the only process that can tell
 * the commands to stop is an http worker, writing to the shared flag the command reads from its own loop
 * ({@see StopCheck}, or the messenger listener).
 *
 * Both events, because the http worker fires different ones depending on what is in its reactor:
 *
 *  - onWorkerStop, always - but only once its reactor has drained, so a worker holding a timer fires it
 *    late, after max_wait_time;
 *  - onWorkerExit, early and repeatedly - but only while something is left in the reactor, such as
 *    HMR's timer in debug. An idle http worker never fires it.
 *
 * Whichever comes first raises, which is idempotent. What neither can say is why the http worker is
 * stopping: a worker_max_request recycle fires the same callbacks. That is why worker_max_request is
 * refused for blocking task worker commands - see {@see Exception\WorkerMaxRequestUnsupported} - rather than told
 * apart here, where nothing makes it possible.
 *
 * Fires many times per shutdown and outside any coroutine; a flag-set is idempotent and needs nothing a
 * coroutine provides, which is why this does nothing else.
 *
 * @see RaiseStopSignalOnTaskWorkerExit for coroutines on, where the task worker raises for itself
 * @see WorkerRetirement for the one exit that must not raise anything at all
 */
final readonly class RaiseStopSignalOnWorkerShutdown implements EventSubscriberInterface
{
    public function __construct(
        private WorkerStopSignal $stopSignal,
        private WorkerRetirement $retirement,
    ) {}

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            WorkerExitedEvent::NAME => 'onWorkerShutdown',
            WorkerStoppedEvent::NAME => 'onWorkerShutdown',
        ];
    }

    public function onWorkerShutdown(): void
    {
        // A worker retiring so it can be replaced is not the server going down, and raising the signal
        // for it would stop the commands its replacement is about to start.
        if ($this->retirement->isRetiring()) {
            return;
        }

        $this->stopSignal->raise();
    }
}

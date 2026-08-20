<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\TaskWorker;

use SwooleBundle\SwooleBundle\Bridge\Symfony\Event\WorkerExitedEvent;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Event\WorkerStoppedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * EXPERIMENTAL. Raises the shared stop flag as soon as any worker learns the server is stopping.
 *
 * Subscribed in every worker, http and task alike, because which one hears it first depends on the mode
 * and both write the same flag.
 *
 * onWorkerExit is what makes this work, and onWorkerStop is only a fallback. In a task worker running
 * commands the ordering is:
 *
 *     4.009s onWorkerExit <- commands still running, this is the usable moment
 *     4.083s command stops
 *     4.084s onWorkerStop <- only now, because it waits for the coroutines to finish
 *
 * Waiting for onWorkerStop would mean waiting for the very thing the flag is supposed to stop.
 *
 * onWorkerExit also fires more than once per shutdown, and outside any coroutine. Setting a flag is
 * both idempotent and free of anything that needs a coroutine to run in, which is the whole reason the
 * handler does nothing else.
 *
 * What it raises is scoped to this worker's generation, so the workers a reload is replacing cannot
 * stop the ones replacing them.
 *
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

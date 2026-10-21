<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\TaskWorker;

use SwooleBundle\SwooleBundle\Bridge\Symfony\Event\WorkerExitedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * EXPERIMENTAL. With coroutines on, raises the shared stop flag from the task worker's own exit, and
 * from nowhere else.
 *
 * A task worker running commands in coroutines is back in its event loop while they run, so its own
 * onWorkerExit fires within milliseconds of the server being asked to stop - while the commands are
 * still running, which is the moment the flag is for. It raises for itself, and needs no other worker
 * to do it.
 *
 * Http workers are left out on purpose, because an http worker's exit says nothing about the server:
 *
 *  - worker_max_request replaces an http worker while the server carries on, and it leaves through the
 *    same callbacks as a worker going down with the server;
 *  - onWorkerExit only fires at all while the worker still has something in its reactor - a timer, HMR's
 *    among them. With one, a recycled http worker fires it as readily as one that is shutting down.
 *
 * Raising from one would stop every command in every task worker each time an http worker was
 * recycled, and nothing would start them again. Task workers are not recycled by worker_max_request,
 * which is the http workers' setting.
 *
 * Fires many times per shutdown and outside any coroutine; a flag-set is idempotent and needs nothing a
 * coroutine provides, which is why this does nothing else.
 *
 * @see RaiseStopSignalOnWorkerShutdown for coroutines off, where the task worker cannot raise for itself
 * @see WorkerRetirement for the exit of a task worker recycling itself, which must not raise anything
 */
final readonly class RaiseStopSignalOnTaskWorkerExit implements EventSubscriberInterface
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
        return [WorkerExitedEvent::NAME => 'onWorkerExited'];
    }

    public function onWorkerExited(WorkerExitedEvent $event): void
    {
        if (!$event->getServer()->taskworker) {
            return;
        }

        // A task worker retiring so it can be replaced is not the server going down, and raising the
        // signal for it would stop the commands its replacement is about to start.
        if ($this->retirement->isRetiring()) {
            return;
        }

        $this->stopSignal->raise();
    }
}

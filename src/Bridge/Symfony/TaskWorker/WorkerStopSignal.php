<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\TaskWorker;

use Swoole\Atomic;

/**
 * EXPERIMENTAL. Shared-memory "the server is going down" flag.
 *
 * A signal handler cannot do this job. Swoole claims the worker signals for itself - a pcntl handler
 * registered inside a worker reports success and then never fires, because the signal is drained
 * through swoole's own reactor - so the shutdown notice has to travel some other way.
 *
 * Which way depends on the mode, and both write the same flag:
 *
 *  - coroutines on: the task worker's own onWorkerExit fires while the commands are still running,
 *    so the worker raises this itself.
 *  - coroutines off: the task worker is blocked in its command and never reaches its own lifecycle
 *    callbacks at all. What does still fire, in a different process, is the http worker's onWorkerStop.
 *    Hence shared memory rather than a plain property.
 *
 * The Atomic has to be allocated before the workers are forked for every process to see the same
 * memory - constructing this in the master is what guarantees that, and WithWorkerStopSignal makes
 * that ordering explicit.
 *
 * @see RaiseStopSignalOnWorkerShutdown for who raises it
 */
final readonly class WorkerStopSignal
{
    private const int RAISED = 1;

    private Atomic $flag;

    public function __construct()
    {
        $this->flag = new Atomic(0);
    }

    public function raise(): void
    {
        $this->flag->set(self::RAISED);
    }

    public function isRaised(): bool
    {
        return $this->flag->get() === self::RAISED;
    }

    /**
     * Only safe before the workers are forked - a reset racing a live worker would lose a shutdown.
     */
    public function reset(): void
    {
        $this->flag->set(0);
    }
}

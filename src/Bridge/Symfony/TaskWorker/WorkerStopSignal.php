<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\TaskWorker;

use Swoole\Atomic;

/**
 * EXPERIMENTAL. Shared-memory "the server is going down" flag, scoped to a generation of workers.
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
 * ## Why a generation and not a boolean
 *
 * Workers exit for three different reasons and only one of them means the server is going down:
 *
 *  - the server is stopping - every worker exits and none is replaced,
 *  - the server is reloading - every worker exits and every one is replaced,
 *  - a worker is retiring - one worker exits, because a command returned on --memory-limit and the
 *    point of the feature is that its process goes away, and the manager forks a replacement.
 *
 * A plain boolean cannot tell them apart. Every one of those exits raises it, nothing lowers it
 * again - the reset below only ever runs in the master, at Server::start() - and so a replacement
 * that starts afterwards reads a flag raised by the worker it replaced and stops the commands it has
 * just started. Permanently: nothing will lower the flag for as long as the server runs.
 *
 * A generation makes the flag say which workers a stop was raised for. Every worker binds itself to
 * the generation it was forked into, a raise records the raiser's own generation rather than
 * "raised", and a worker is only being asked to stop when the two match. A stop raised by the
 * workers a reload is replacing therefore lands on the generation being replaced and not on the one
 * replacing it, while a stop raised during a real shutdown lands on the generation every live worker
 * is in - which is the whole point of the flag.
 *
 * That leaves the retiring worker, whose replacement is forked into the same generation it was in,
 * so a generation cannot tell those two apart either. Nothing shared can: the difference is not
 * about when the worker exits but about why, and only the worker itself knows. It says so through
 * WorkerRetirement, which suppresses the raise.
 *
 * The Atomics have to be allocated before the workers are forked for every process to see the same
 * memory - constructing this in the master is what guarantees that, and WithWorkerStopSignal makes
 * that ordering explicit.
 *
 * @see RaiseStopSignalOnWorkerShutdown for who raises it
 * @see WorkerRetirement for the exit that must not raise it
 * @see WithWorkerStopSignal for who opens a generation
 */
final class WorkerStopSignal
{
    /**
     * Lower than any generation, so a signal nobody has raised matches no worker.
     */
    private const int NONE = 0;

    /**
     * The generation an unconfigured signal is already in, so that raising one and asking it works
     * without opening a generation first. Nothing outside the master ever needs to.
     */
    private const int FIRST = 1;

    private readonly Atomic $generation;

    private readonly Atomic $stopGeneration;

    /**
     * The generation this process was forked into. Null until enterGeneration(), which is to say null
     * in the master and in the manager - neither of them is ever asked to stop.
     */
    private ?int $ownGeneration = null;

    public function __construct()
    {
        $this->generation = new Atomic(self::FIRST);
        $this->stopGeneration = new Atomic(self::NONE);
    }

    /**
     * Opens a generation, in a process that has no workers of the new one yet: the master before the
     * first fork, and the manager before a reload replaces the current workers.
     *
     * Deliberately does not lower an already raised signal. It does not need to - a stop recorded
     * against an earlier generation matches none of the workers in this one - and lowering it would
     * take the notice away from the workers that are still winding down and are polling for exactly
     * that.
     */
    public function newGeneration(): void
    {
        $this->generation->add(1);
    }

    /**
     * Binds this process to the generation it was forked into, from onWorkerStart.
     *
     * Every worker does this, http workers included: with coroutines off an http worker is what
     * raises the signal for a task worker that is blocked in its command, so it needs a generation of
     * its own to raise it for.
     */
    public function enterGeneration(): void
    {
        $this->ownGeneration = $this->generation->get();
    }

    public function raise(): void
    {
        $this->stopGeneration->set($this->ownGeneration ?? $this->generation->get());
    }

    public function isRaised(): bool
    {
        // NONE matches no generation, so an unraised signal answers no without a special case.
        return $this->stopGeneration->get() === ($this->ownGeneration ?? $this->generation->get());
    }
}

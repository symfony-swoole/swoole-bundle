<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\TaskWorker;

use ArrayObject;
use SwooleBundle\SwooleBundle\Common\Adapter\Swoole;

/**
 * EXPERIMENTAL. What a long running command asks, between units of work, to learn it should stop.
 *
 * ```php
 * while (!$this->stopCheck->shouldStop()) {
 *     $this->runOneBatch();
 * }
 * ```
 *
 * This is the stop path that works in every mode, because it needs nothing from the worker but the
 * command's own loop. The others each have a gap:
 *
 *  - signals never reach a command inside a swoole worker - swoole claims them;
 *  - the watchdog hands a command the signal it subscribes to, but only with coroutines on, since it
 *    is a coroutine itself, and only to a command that subscribes to one;
 *  - lifecycle callbacks are delivered through the worker's event loop, which a command blocking its
 *    worker with coroutines off keeps the worker from ever getting back to.
 *
 * A read of shared memory needs none of that. With coroutines off this is the only way a command other
 * than messenger:consume - which the bundle checks for, on messenger's own WorkerRunningEvent - learns
 * the server is going down before it is force-terminated.
 *
 * It answers yes for two reasons:
 *
 *  - the server is stopping or reloading, which is {@see WorkerStopSignal};
 *  - another command in the same task worker has ended, and the group is winding down so the worker can
 *    be recycled together, which is the group's {@see StopState}. With coroutines on, a command that
 *    does not subscribe to signals has no other way of hearing it, and the group would wait on it
 *    forever.
 *
 * ### Kept in the coroutine's context, and looked up through its ancestors
 *
 * Every group has a StopState of its own, so it is stored in the context of the coroutine running the
 * command rather than in a field here, where the groups would overwrite each other. A lookup walks up the
 * parent chain, so a command asking from a coroutine it spawned still finds its group. With coroutines
 * off there is no coroutine to store it in, and the field holds it instead - safe, because such a
 * process runs exactly one command.
 *
 * Registered whether or not task worker commands are configured, so a command written against it runs
 * unchanged under a supervisor: there it always answers no, and stopping on SIGTERM is left to the
 * command's own signal handling, which works outside a swoole worker.
 *
 * @see docs/swoole-task-worker-commands.md
 */
final class StopCheck
{
    /**
     * Namespaced, because a coroutine's context is shared with everything else running in it.
     */
    private const string CONTEXT_KEY = 'swoole_bundle.stop_state';

    /**
     * Mirrors RunningCommand: generous next to what a worker actually nests, bounded because a walk over
     * a chain that somehow loops would take the worker with it.
     */
    private const int MAX_ANCESTORS = 16;

    private ?StopState $processState = null;

    /**
     * @param WorkerStopSignal|null $stopSignal null when no task worker commands are configured
     */
    public function __construct(
        private readonly Swoole $swoole,
        private readonly ?WorkerStopSignal $stopSignal = null,
    ) {}

    public function shouldStop(): bool
    {
        if ($this->stopSignal?->isRaised() ?? false) {
            return true;
        }

        return $this->boundState()?->isRequested() ?? false;
    }

    /**
     * Ties the group a command belongs to onto the coroutine running it. Called by
     * {@see CommandGroupRunner} before the command starts; nothing else has a reason to.
     *
     * @internal
     */
    public function bind(StopState $state): void
    {
        $context = $this->contextOfTheRunningCoroutine();

        if ($context === null) {
            $this->processState = $state;

            return;
        }

        $context[self::CONTEXT_KEY] = $state;
    }

    private function boundState(): ?StopState
    {
        $cid = $this->runningCoroutineId();

        for ($step = 0; $step < self::MAX_ANCESTORS && $cid !== null; $step++) {
            $state = $this->swoole->getCoroutineContext($cid)[self::CONTEXT_KEY] ?? null;

            if ($state instanceof StopState) {
                return $state;
            }

            $parent = $this->swoole->getParentCoroutineId($cid);
            $cid = $parent > 0 ? $parent : null;
        }

        return $this->processState;
    }

    /**
     * @return ArrayObject<array-key, mixed>|null
     */
    private function contextOfTheRunningCoroutine(): ?ArrayObject
    {
        $cid = $this->runningCoroutineId();

        return $cid === null ? null : $this->swoole->getCoroutineContext($cid);
    }

    /**
     * Null outside a coroutine, which the engines answer -1 for.
     */
    private function runningCoroutineId(): ?int
    {
        $cid = $this->swoole->getCoroutineId();

        return $cid > 0 ? $cid : null;
    }
}

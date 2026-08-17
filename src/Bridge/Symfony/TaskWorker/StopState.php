<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\TaskWorker;

/**
 * EXPERIMENTAL. Worker-local "wind this group down" flag.
 *
 * Distinct from WorkerStopSignal, which is shared memory and means the whole server is stopping. This
 * one is a plain property because it never crosses a process boundary: it only says that one command in
 * this task worker has ended, so the rest should stop too and let the process be recycled together.
 */
final class StopState
{
    private bool $requested = false;

    public function request(): void
    {
        $this->requested = true;
    }

    /**
     * Reads a property another coroutine writes: the command coroutine sets it on its way out while a
     * watchdog is sitting in a loop on this.
     */
    public function isRequested(): bool
    {
        return $this->requested;
    }
}

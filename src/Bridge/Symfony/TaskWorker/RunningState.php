<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\TaskWorker;

/**
 * EXPERIMENTAL. Whether one command is still running, shared with the watchdog watching it.
 *
 * An object rather than a bool passed by reference: the watchdog lives in its own coroutine and has to
 * see the command finish, and passing `&$running` into the closure is what the coding standard
 * disallows. Handing round one object achieves the same thing, since both sides hold the same instance.
 *
 * Without it the watchdog would have no way to learn the command returned on its own, and would go on
 * polling for the life of the worker.
 */
final class RunningState
{
    private bool $running = true;

    public function markFinished(): void
    {
        $this->running = false;
    }

    /**
     * Reads a flag the command coroutine writes on its way out, while the watchdog loops on this.
     */
    public function isRunning(): bool
    {
        return $this->running;
    }
}

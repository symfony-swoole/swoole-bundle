<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\TaskWorker;

/**
 * EXPERIMENTAL. Process local "this worker is retiring, the server is not going down" flag.
 *
 * A worker that stops itself so the manager will fork a replacement - which is what a command
 * returning on --memory-limit asks for - leaves through the same onWorkerExit and onWorkerStop as a
 * worker going down with the server. The shutdown signal cannot tell those apart on its own: the
 * replacement is forked into the same generation the retiring worker was in, so a raise from the one
 * reads as a stop meant for the other, and the replacement stops the commands it has just started.
 *
 * Which is to say the difference is not about when the worker exits but about why, and the only
 * process that knows why is the one exiting. This is where it says so.
 *
 * Process local on purpose: it describes this process and nothing else, and the replacement is a
 * different process, which starts with it unset and runs its commands normally.
 *
 * @see WorkerStopSignal for the generation that covers reloads, and why it cannot cover this
 */
final class WorkerRetirement
{
    private bool $retiring = false;

    public function retire(): void
    {
        $this->retiring = true;
    }

    public function isRetiring(): bool
    {
        return $this->retiring;
    }
}

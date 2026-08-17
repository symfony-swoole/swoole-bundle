<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\TaskWorker;

/**
 * EXPERIMENTAL. Runs a task worker's group of commands, in whichever shape the platform allows.
 */
interface CommandGroupExecutor
{
    /**
     * Each command in its own coroutine; returns as soon as they are spawned, leaving the task worker
     * free to run its reactor and go on serving tasks.
     *
     * @param list<string> $commandLines
     */
    public function runInCoroutines(WorkerControl $control, int $workerId, array $commandLines): void;

    /**
     * Runs the command in the calling process and does not return until it has finished. The task
     * worker is dedicated to it for that whole time.
     */
    public function runBlocking(WorkerControl $control, int $workerId, string $commandLine): void;
}

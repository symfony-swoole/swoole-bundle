<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\TaskWorker;

use Swoole\Atomic;

/**
 * EXPERIMENTAL. Says that a task worker gave up on its commands, across the processes that have to know.
 *
 * Two of them do, and neither is the one that finds out. The task worker decides, the master is what
 * brings the server down, and the process that ran `swoole:server:run` is what has to exit non-zero so
 * that whatever supervises the container - Kubernetes, systemd, a compose restart policy - sees a
 * failure rather than a clean stop. An Atomic is the only thing all three share.
 *
 * Allocated in the constructor, which is to say in the process that builds the container before any
 * worker is forked: shared memory has to exist before the fork for the children to see the same of it.
 *
 * One flag for the server rather than one per worker, because what it asks for is the end of the server,
 * and a second worker raising it after the first says nothing new.
 */
final readonly class TaskWorkerFailure
{
    private const int NONE = 0;

    private const int RAISED = 1;

    private Atomic $failed;

    public function __construct()
    {
        $this->failed = new Atomic(self::NONE);
    }

    public function raise(): void
    {
        $this->failed->set(self::RAISED);
    }

    public function isRaised(): bool
    {
        return $this->failed->get() === self::RAISED;
    }
}

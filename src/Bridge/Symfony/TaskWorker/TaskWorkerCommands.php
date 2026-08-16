<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\TaskWorker;

/**
 * EXPERIMENTAL. Which console commands each task worker runs.
 *
 * One configured group is one task worker: group 0 goes to the first task worker, group 1 to the
 * second, and so on. A group holding more than one command means those commands share a single task
 * worker process, each in its own coroutine - which is why groups larger than one are rejected at
 * compile time when coroutine support is off.
 *
 * @see docs/swoole-task-worker-commands.md
 */
final readonly class TaskWorkerCommands
{
    /**
     * @param array<int, list<string>> $groups keyed by task worker index, not by swoole worker id
     */
    public function __construct(private array $groups) {}

    /**
     * @return list<string>
     */
    public function forTaskWorker(int $taskWorkerIndex): array
    {
        return $this->groups[$taskWorkerIndex] ?? [];
    }

    public function taskWorkersRequired(): int
    {
        return count($this->groups);
    }

    public function isEmpty(): bool
    {
        return $this->groups === [];
    }
}

<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\ConcurrentTasks;

use Closure;
use SwooleBundle\SwooleBundle\Server\HttpServer;

final class ConcurrentTasks
{
    public function __construct(
        private readonly HttpServer $httpServer,
    ) {}

    /**
     * @param array<int|string, callable> $callbacks
     * @param float $timeout The maximum time to wait for all tasks to complete, in seconds.
     * @return array<int|string, mixed> The results of the tasks keyed/indexed in the same order as input callbacks.
     *                                  If a task fails, its result will be false.
     *                                  If a task exceeds the timeout, its result will be null.
     */
    public function run(array $callbacks, float $timeout = 5.0): array
    {
        if (empty($callbacks)) {
            return [];
        }

        $tasks = [];

        foreach ($callbacks as $callback) {
            $tasks[] = Task::create(Closure::fromCallable($callback));
        }

        /** @var array<int|string, mixed>|false $results */
        $results = $this->httpServer->getServer()->taskWaitMulti($tasks, $timeout);

        if ($results === false) {
            return [];
        }

        return $results;
    }
}

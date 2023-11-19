<?php

declare(strict_types=1);

namespace K911\Swoole\Metrics;

final class Metrics
{
    public function __construct(
        private int $requestCount,
        private int $upTimeInSeconds,
        private int $activeConnections,
        private int $acceptedConnections,
        private int $closedConnections,
        private int $totalWorkers,
        private int $activeWorkers,
        private int $idleWorkers,
        private int $runningCoroutines,
        private int $tasksInQueue,
    ) {
    }

    public function requestCount(): int
    {
        return $this->requestCount;
    }

    public function upTimeInSeconds(): int
    {
        return $this->upTimeInSeconds;
    }

    public function activeConnections(): int
    {
        return $this->activeConnections;
    }

    public function acceptedConnections(): int
    {
        return $this->acceptedConnections;
    }

    public function closedConnections(): int
    {
        return $this->closedConnections;
    }

    public function totalWorkers(): int
    {
        return $this->totalWorkers;
    }

    public function activeWorkers(): int
    {
        return $this->activeWorkers;
    }

    public function idleWorkers(): int
    {
        return $this->idleWorkers;
    }

    public function runningCoroutines(): int
    {
        return $this->runningCoroutines;
    }

    public function tasksInQueue(): int
    {
        return $this->tasksInQueue;
    }
}

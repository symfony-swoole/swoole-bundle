<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Metrics;

final class Metrics
{
    public function __construct(
        private readonly int $requestCount,
        private readonly int $upTimeInSeconds,
        private readonly int $activeConnections,
        private readonly int $acceptedConnections,
        private readonly int $closedConnections,
        private readonly int $totalWorkers,
        private readonly int $activeWorkers,
        private readonly int $idleWorkers,
        private readonly int $runningCoroutines,
        private readonly int $tasksInQueue,
    ) {}

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

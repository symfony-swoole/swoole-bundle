<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Container\ServicePool;

final class ServicePoolContainer
{
    /**
     * @var array<int, ServicePoolEntry<object>>
     */
    private array $poolEntries = [];

    /**
     * @var array<int, array<int, ServicePoolEntry<object>>>
     */
    private array $poolEntriesToReset = [];

    /**
     * @param array<int, ServicePoolEntry<object>> $poolEntries
     */
    public function __construct(array $poolEntries)
    {
        foreach ($poolEntries as $poolEntry) {
            $this->addPoolEntry($poolEntry);
        }
    }

    /**
     * @param ServicePoolEntry<object> $poolEntry
     */
    public function addPoolEntry(ServicePoolEntry $poolEntry): void
    {
        $this->poolEntries[] = $poolEntry;

        if ($poolEntry->resetter === null) {
            return;
        }

        if (!isset($this->poolEntriesToReset[$poolEntry->resetPriority])) {
            $this->poolEntriesToReset[$poolEntry->resetPriority] = [];
        }

        $this->poolEntriesToReset[$poolEntry->resetPriority][] = $poolEntry;
    }

    public function releaseFromCoroutine(): void
    {
        // the reset cycle has to be executed before releasing the services back to the pool
        // to not get assigned too early to other coroutines which can cause deadlocks
        // during the reset cycle if there are dependencies among released and not released services
        foreach ($this->poolEntriesToReset as $prioritizedPoolEntries) {
            foreach ($prioritizedPoolEntries as $poolEntry) {
                if ($poolEntry->resetter === null) {
                    continue;
                }

                $instance = $poolEntry->pool->getAssigned();

                if ($instance === null) {
                    continue;
                }

                $poolEntry->resetter->reset($instance);
            }
        }

        foreach ($this->poolEntries as $poolEntry) {
            $poolEntry->pool->releaseFromCoroutine();
        }
    }

    public function count(): int
    {
        return count($this->poolEntries);
    }
}

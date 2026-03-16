<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Container\ServicePool;

final class ServicePoolContainer
{
    /**
     * @param array<int, array<ServicePool<object>>> $pools
     */
    public function __construct(private array $pools) {}

    /**
     * @param ServicePool<object> $pool
     */
    public function addPool(ServicePool $pool, int $priority = 0): void
    {
        $this->pools[$priority][] = $pool;
        krsort($this->pools);
    }

    public function releaseFromCoroutine(int $cId): void
    {
        foreach ($this->pools as $poolsWithPriority) {
            foreach ($poolsWithPriority as $pool) {
                $pool->releaseFromCoroutine($cId);
            }
        }
    }

    public function count(): int
    {
        return array_reduce(
            $this->pools,
            static fn(int $count, array $poolsWithPriority): int => $count + count($poolsWithPriority),
            0,
        );
    }
}

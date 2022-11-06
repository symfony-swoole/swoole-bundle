<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Symfony\Container\ServicePool;

use K911\Swoole\Bridge\Symfony\Container\StabilityChecker;
use K911\Swoole\Component\Locking\Lock;
use K911\Swoole\Component\Locking\Locking;

abstract class BaseServicePool implements ServicePool
{
    private string $lockingKey;

    private Locking $locking;

    private ?StabilityChecker $stabilityChecker;

    private ?Lock $lock = null;

    private int $instancesLimit;

    private int $assignedCount = 0;

    /**
     * @var array<int, object>
     */
    private array $freePool = [];

    /**
     * @var array<int, object>
     */
    private array $assignedPool = [];

    public function __construct(
        string $lockingKey,
        Locking $locking,
        int $instancesLimit = 50,
        ?StabilityChecker $stabilityChecker = null
    ) {
        $this->lockingKey = $lockingKey;
        $this->instancesLimit = $instancesLimit;
        $this->locking = $locking;
        $this->stabilityChecker = $stabilityChecker;
    }

    public function get(): object
    {
        $cId = $this->getCoroutineId();

        if (isset($this->assignedPool[$cId])) {
            return $this->assignedPool[$cId];
        }

        if ($this->assignedCount >= $this->instancesLimit) {
            // this will wait until a different coroutine will release the lock
            $this->lockPool();
        }

        ++$this->assignedCount;

        if (!empty($this->freePool)) {
            return $this->assignedPool[$cId] = array_shift($this->freePool);
        }

        return $this->assignedPool[$cId] = $this->newServiceInstance();
    }

    public function releaseForCoroutine(int $cId): void
    {
        if (!isset($this->assignedPool[$cId])) {
            return;
        }

        $service = $this->assignedPool[$cId];
        unset($this->assignedPool[$cId]);
        --$this->assignedCount;

        if (!$this->isServiceStable($service)) {
            $this->unlockPool();

            return;
        }

        $this->freePool[] = $service;
        $this->unlockPool();
    }

    abstract protected function newServiceInstance(): object;

    private function getCoroutineId(): int
    {
        return \Co::getCid();
    }

    private function isServiceStable(object $service): bool
    {
        return null === $this->stabilityChecker || $this->stabilityChecker->isStable($service);
    }

    private function lockPool(): void
    {
        $this->lock = $this->locking->acquire($this->lockingKey);
    }

    private function unlockPool(): void
    {
        if (null === $this->lock) {
            return;
        }

        $lock = $this->lock;
        $this->lock = null;
        $lock->release();
    }
}

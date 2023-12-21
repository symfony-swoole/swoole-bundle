<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Symfony\Container\ServicePool;

use K911\Swoole\Bridge\Symfony\Container\Resetter;
use K911\Swoole\Bridge\Symfony\Container\StabilityChecker;
use K911\Swoole\Component\Locking\Mutex;

/**
 * @template T of object
 *
 * @template-implements ServicePool<T>
 */
abstract class BaseServicePool implements ServicePool
{
    private int $assignedCount = 0;

    /**
     * @var array<int, T>
     */
    private array $freePool = [];

    /**
     * @var array<int, T>
     */
    private array $assignedPool = [];

    public function __construct(
        private readonly Mutex $mutex,
        private readonly int $instancesLimit = 50,
        private readonly ?Resetter $resetter = null,
        private readonly ?StabilityChecker $stabilityChecker = null
    ) {
    }

    /**
     * @return T
     */
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
            $assigned = array_shift($this->freePool);

            if (null !== $this->resetter) {
                $this->resetter->reset($assigned);
            }

            return $this->assignedPool[$cId] = $assigned;
        }

        return $this->assignedPool[$cId] = $this->newServiceInstance();
    }

    public function releaseFromCoroutine(int $cId): void
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

    /**
     * @return T
     */
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
        $this->mutex->acquire();
    }

    private function unlockPool(): void
    {
        if (!$this->mutex->isAcquired()) {
            return;
        }

        $this->mutex->release();
    }
}

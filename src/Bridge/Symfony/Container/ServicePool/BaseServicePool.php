<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Container\ServicePool;

use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Initializer;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Resetter;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\StabilityChecker;
use SwooleBundle\SwooleBundle\Common\Adapter\Swoole;
use SwooleBundle\SwooleBundle\Component\Locking\Mutex;

/**
 * @template T of object
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
        private readonly Swoole $swoole,
        private readonly Mutex $mutex,
        private readonly int $instancesLimit = 50,
        private readonly ?Resetter $resetter = null,
        private readonly ?StabilityChecker $stabilityChecker = null,
        private readonly ?Initializer $initializer = null,
    ) {}

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

        $this->assignedCount++;

        return $this->assignedPool[$cId] = $this->getServiceToAssign();
    }

    public function releaseFromCoroutine(int $cId): void
    {
        if (!isset($this->assignedPool[$cId])) {
            return;
        }

        $service = $this->assignedPool[$cId];
        unset($this->assignedPool[$cId]);
        $this->assignedCount--;

        if (!$this->isServiceStable($service)) {
            $this->unlockPool();

            return;
        }

        $this->resetter?->reset($service);

        $this->freePool[] = $service;
        $this->unlockPool();
    }

    /**
     * @return T
     */
    abstract protected function newServiceInstance(): object;

    /**
     * @return T
     */
    private function getServiceToAssign(): object
    {
        if (!empty($this->freePool)) {
            $assigned = array_shift($this->freePool);
        } else {
            $assigned = $this->newServiceInstance();
        }

        $this->initializer?->initialize($assigned);

        return $assigned;
    }

    private function getCoroutineId(): int
    {
        return $this->swoole->getCoroutineId();
    }

    private function isServiceStable(object $service): bool
    {
        return $this->stabilityChecker === null || $this->stabilityChecker->isStable($service);
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

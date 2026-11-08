<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Container\ServicePool;

use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Disposer;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Initializer;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\StabilityChecker;
use SwooleBundle\SwooleBundle\Common\Adapter\Swoole;
use SwooleBundle\SwooleBundle\Component\Locking\Mutex;

/**
 * Every coroutine that asks for the service gets an instance of its own, up to $instancesLimit at a time, and gives
 * it back when it ends; an instance given back waits in the free pool for the next coroutine.
 *
 * The free pool keeps as many instances as were ever in use at once, unless $idleLimit says otherwise: a burst of
 * concurrent requests leaves that many instances behind for the life of the worker, and where an instance holds a
 * server's session - a database connection - each one is a session the server keeps open for nothing. With an idle
 * limit, an instance given back to a free pool that already holds that many is dropped instead, and handed to the
 * $disposer, which lets go of what it holds.
 *
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
        private readonly ?StabilityChecker $stabilityChecker = null,
        private readonly ?Initializer $initializer = null,
        private readonly ?int $idleLimit = null,
        private readonly ?Disposer $disposer = null,
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

    public function getAssigned(): ?object
    {
        $cId = $this->getCoroutineId();

        return $this->assignedPool[$cId] ?? null;
    }

    public function releaseFromCoroutine(): void
    {
        $cId = $this->getCoroutineId();

        if (!isset($this->assignedPool[$cId])) {
            return;
        }

        $service = $this->assignedPool[$cId];
        unset($this->assignedPool[$cId]);
        $this->assignedCount--;

        if (!$this->isServiceStable($service) || $this->isFreePoolFull()) {
            $this->unlockPool();
            $this->dispose($service);

            return;
        }

        $this->freePool[] = $service;
        $this->unlockPool();
    }

    public function discardUnstableAssigned(): void
    {
        $cId = $this->getCoroutineId();

        if (!isset($this->assignedPool[$cId])) {
            return;
        }

        if ($this->isServiceStable($this->assignedPool[$cId])) {
            return;
        }

        // deliberately not returned to the free pool - the same reasoning as the unstable branch of
        // releaseFromCoroutine(), an instance that failed the stability check must not be handed out
        // again. Dropping it here is the whole point: the next get() builds a new one.
        $service = $this->assignedPool[$cId];
        unset($this->assignedPool[$cId]);
        $this->assignedCount--;
        $this->unlockPool();
        $this->dispose($service);
    }

    public function drain(): void
    {
        $drained = $this->freePool;
        $this->freePool = [];

        foreach ($drained as $service) {
            $this->dispose($service);
        }
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

    private function isFreePoolFull(): bool
    {
        return $this->idleLimit !== null && count($this->freePool) >= $this->idleLimit;
    }

    /**
     * Called once the instance is out of the pool and the pool's lock is given back, so that a disposer that throws
     * leaves the pool as it would be had the instance been disposed of.
     *
     * @param T $service
     */
    private function dispose(object $service): void
    {
        $this->disposer?->dispose($service);
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

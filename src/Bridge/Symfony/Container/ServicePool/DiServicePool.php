<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Symfony\Container\ServicePool;

use K911\Swoole\Bridge\Symfony\Container\StabilityChecker;
use K911\Swoole\Component\Locking\CoroutineLocking;
use K911\Swoole\Component\Locking\Lock;
use K911\Swoole\Component\Locking\Locking;
use Symfony\Component\DependencyInjection\Container;

final class DiServicePool implements ServicePool
{
    private string $wrappedServiceId;

    private Container $container;

    private ?StabilityChecker $stabilityChecker;

    /**
     * @var array<int, object>
     */
    private array $freePool = [];

    /**
     * @var array<int, object>
     */
    private array $assignedPool = [];

    private int $limit = 50;

    private int $assignedCount = 0;

    private ?Lock $lock = null;

    private static ?Locking $locking = null;

    public function __construct(
        string $wrappedServiceId,
        Container $container,
        ?StabilityChecker $stabilityChecker = null
    ) {
        $this->wrappedServiceId = $wrappedServiceId;
        $this->container = $container;
        $this->stabilityChecker = $stabilityChecker;
        self::$locking = CoroutineLocking::init(self::$locking);
    }

    public function get(): object
    {
        $cId = $this->getCoroutineId();

        if (isset($this->assignedPool[$cId])) {
            return $this->assignedPool[$cId];
        }

        if ($this->assignedCount >= $this->limit) {
            // this will wait until a different coroutine will release the lock
            $this->lockPool();
        }

        ++$this->assignedCount;

        if (!empty($this->freePool)) {
            return $this->assignedPool[$cId] = array_shift($this->freePool);
        }

        return $this->assignedPool[$cId] = $this->container->get($this->wrappedServiceId);
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
        $this->lock = self::$locking->acquire($this->wrappedServiceId);
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

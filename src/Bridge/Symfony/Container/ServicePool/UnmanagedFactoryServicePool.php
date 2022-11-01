<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Symfony\Container\ServicePool;

final class UnmanagedFactoryServicePool implements ServicePool
{
    /**
     * @var \Closure(): object
     */
    private \Closure $instantiator;

    /**
     * @var array<int, object>
     */
    private array $freePool = [];

    /**
     * @var array<int, object>
     */
    private array $assignedPool = [];

    public function __construct(\Closure $instantiator)
    {
        $this->instantiator = $instantiator;
    }

    public function get(): object
    {
        $cId = $this->getCoroutineId();

        if (isset($this->assignedPool[$cId])) {
            return $this->assignedPool[$cId];
        }

        if (!empty($this->freePool)) {
            return $this->assignedPool[$cId] = array_shift($this->freePool);
        }

        $instantiator = $this->instantiator;

        return $this->assignedPool[$cId] = $instantiator();
    }

    public function releaseForCoroutine(int $cId): void
    {
        if (!isset($this->assignedPool[$cId])) {
            return;
        }

        $service = $this->assignedPool[$cId];
        unset($this->assignedPool[$cId]);
        $this->freePool[] = $service;
    }

    private function getCoroutineId(): int
    {
        return \Co::getCid();
    }
}

<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Symfony\Container\ServicePool;

use stdClass;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\ServicePool\ServicePool;

/**
 * A pool that hands out what it was given and counts how often it was released.
 *
 * @template-implements ServicePool<object>
 */
final class ServicePoolSpy implements ServicePool
{
    private int $releaseFromCoroutineCallCount = 0;

    public function __construct(
        private object $toReturnFromGet = new stdClass(),
        private ?object $assigned = null,
    ) {}

    public function get(): object
    {
        return $this->toReturnFromGet;
    }

    public function getAssigned(): ?object
    {
        return $this->assigned;
    }

    public function releaseFromCoroutine(): void
    {
        $this->releaseFromCoroutineCallCount++;
    }

    public function releaseFromCoroutineCallCount(): int
    {
        return $this->releaseFromCoroutineCallCount;
    }
}

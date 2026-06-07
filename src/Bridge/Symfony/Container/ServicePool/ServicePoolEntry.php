<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Container\ServicePool;

use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Resetter;

/**
 * @template T of object
 */
final readonly class ServicePoolEntry
{
    /**
     * @param ServicePool<T> $pool
     */
    public function __construct(
        public ServicePool $pool,
        public ?Resetter $resetter = null,
        public int $resetPriority = 0,
    ) {}
}

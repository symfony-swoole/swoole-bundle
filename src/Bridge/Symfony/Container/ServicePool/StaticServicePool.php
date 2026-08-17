<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Container\ServicePool;

/**
 * @template T of object
 * @template-implements ServicePool<T>
 */
final readonly class StaticServicePool implements ServicePool
{
    /**
     * @param T $instance
     */
    public function __construct(
        private object $instance,
    ) {}

    /**
     * @return T
     */
    public function get(): object
    {
        return $this->instance;
    }

    /**
     * @return T
     */
    public function getAssigned(): object
    {
        return $this->instance;
    }

    public function releaseFromCoroutine(): void
    {
        // no need to release anything
    }

    public function discardUnstableAssigned(): void
    {
        // the one instance is the pool - there is nothing to replace it with
    }
}

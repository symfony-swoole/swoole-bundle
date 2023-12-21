<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Symfony\Container\ServicePool;

use K911\Swoole\Bridge\Symfony\Container\Resetter;
use K911\Swoole\Component\Locking\Mutex;

/**
 * @template T of object
 *
 * @template-extends BaseServicePool<T>
 */
final class UnmanagedFactoryServicePool extends BaseServicePool
{
    /**
     * @param \Closure(): T $instantiator
     */
    public function __construct(
        private \Closure $instantiator,
        Mutex $mutex,
        int $instancesLimit = 50,
        ?Resetter $resetter = null
    ) {
        parent::__construct($mutex, $instancesLimit, $resetter);
    }

    /**
     * @return T
     */
    protected function newServiceInstance(): object
    {
        $instantiator = $this->instantiator;

        return $instantiator();
    }
}

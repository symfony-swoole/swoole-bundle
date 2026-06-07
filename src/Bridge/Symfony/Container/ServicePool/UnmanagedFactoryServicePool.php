<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Container\ServicePool;

use Closure;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Initializer;
use SwooleBundle\SwooleBundle\Common\Adapter\Swoole;
use SwooleBundle\SwooleBundle\Component\Locking\Mutex;

/**
 * @template T of object
 * @template-extends BaseServicePool<T>
 */
final class UnmanagedFactoryServicePool extends BaseServicePool
{
    /**
     * @param Closure(): T $instantiator
     */
    public function __construct(
        private readonly Closure $instantiator,
        Swoole $swoole,
        Mutex $mutex,
        int $instancesLimit = 50,
        ?Initializer $initializer = null,
    ) {
        parent::__construct($swoole, $mutex, $instancesLimit, null, $initializer);
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

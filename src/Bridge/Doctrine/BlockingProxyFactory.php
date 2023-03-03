<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Doctrine;

use Doctrine\Common\Proxy\Proxy;
use Doctrine\ORM\Proxy\ProxyFactory;
use K911\Swoole\Component\Locking\FirstTimeOnly\FirstTimeOnlyMutex;
use K911\Swoole\Component\Locking\FirstTimeOnly\FirstTimeOnlyMutexFactory;

final class BlockingProxyFactory extends ProxyFactory
{
    /**
     * @var array<string, FirstTimeOnlyMutex>
     */
    private array $mutexes = [];

    public function __construct(
        private ProxyFactory $wrapped,
        private FirstTimeOnlyMutexFactory $mutexFactory
    ) {
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $className
     *
     * @return Proxy<T>
     */
    public function getProxy($className, array $identifier)
    {
        $mutex = $this->getMutex($className);

        try {
            $mutex->acquire();
            $proxy = $this->wrapped->getProxy($className, $identifier);
        } finally {
            $mutex->release();
        }

        return $proxy;
    }

    /**
     * {@inheritDoc}
     *
     * @return int
     */
    public function generateProxyClasses(array $classes, $proxyDir = null)
    {
        return $this->wrapped->generateProxyClasses($classes, $proxyDir);
    }

    /**
     * @template T of object
     *
     * @param Proxy<T> $proxy
     *
     * @return Proxy<T>
     */
    public function resetUninitializedProxy(Proxy $proxy)
    {
        return $this->wrapped->resetUninitializedProxy($proxy);
    }

    private function getMutex(string $className): FirstTimeOnlyMutex
    {
        if (!isset($this->mutexes[$className])) {
            $this->mutexes[$className] = $this->mutexFactory->newMutex();
        }

        return $this->mutexes[$className];
    }
}

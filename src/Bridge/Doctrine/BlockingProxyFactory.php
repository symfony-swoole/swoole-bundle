<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Doctrine;

use Doctrine\Common\Proxy\Proxy;
use Doctrine\ORM\Proxy\ProxyFactory;
use SwooleBundle\SwooleBundle\Component\Locking\FirstTimeOnly\FirstTimeOnlyMutex;
use SwooleBundle\SwooleBundle\Component\Locking\FirstTimeOnly\FirstTimeOnlyMutexFactory;

final class BlockingProxyFactory extends ProxyFactory
{
    /**
     * @var array<string, FirstTimeOnlyMutex>
     */
    private array $mutexes = [];

    public function __construct(
        private readonly ProxyFactory $wrapped,
        private readonly FirstTimeOnlyMutexFactory $mutexFactory,
    ) {}

    /**
     * @template T of object
     * @param class-string<T> $className
     * @param array<mixed> $identifier
     * @return Proxy<T>
     */
    public function getProxy($className, array $identifier): Proxy
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
     */
    public function generateProxyClasses(array $classes, $proxyDir = null): int
    {
        return $this->wrapped->generateProxyClasses($classes, $proxyDir);
    }

    /**
     * @template T of object
     * @param Proxy<T> $proxy
     * @return Proxy<T>
     */
    public function resetUninitializedProxy(Proxy $proxy): Proxy
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

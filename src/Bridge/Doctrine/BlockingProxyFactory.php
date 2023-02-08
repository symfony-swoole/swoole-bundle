<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Doctrine;

use Doctrine\Common\Proxy\Proxy;
use Doctrine\ORM\Proxy\ProxyFactory;
use K911\Swoole\Component\Locking\FirstTimeOnlyLocking;
use K911\Swoole\Component\Locking\Locking;

final class BlockingProxyFactory extends ProxyFactory
{
    private static ?Locking $locking = null;

    public function __construct(private ProxyFactory $wrapped)
    {
        if (null === self::$locking) {
            self::$locking = FirstTimeOnlyLocking::init();
        }
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
        $lock = self::$locking->acquire($className);

        try {
            $proxy = $this->wrapped->getProxy($className, $identifier);
        } finally {
            $lock->release();
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
}

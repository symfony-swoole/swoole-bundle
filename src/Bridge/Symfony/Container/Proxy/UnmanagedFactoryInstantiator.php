<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Symfony\Container\Proxy;

use K911\Swoole\Bridge\Symfony\Container\ServicePool\ServicePoolContainer;
use K911\Swoole\Bridge\Symfony\Container\ServicePool\UnmanagedFactoryServicePool;
use K911\Swoole\Component\Locking\Locking;
use ProxyManager\Factory\AccessInterceptorValueHolderFactory;
use ProxyManager\Proxy\AccessInterceptorInterface;
use ProxyManager\Proxy\AccessInterceptorValueHolderInterface;
use ProxyManager\Proxy\ValueHolderInterface;

final class UnmanagedFactoryInstantiator
{
    private AccessInterceptorValueHolderFactory $proxyFactory;

    private Instantiator $instantiator;

    private ServicePoolContainer $servicePoolContainer;

    private ProxyDirectoryHandler $proxyDirHandler;

    private Locking $limitLocking;

    private Locking $newInstanceLocking;

    public function __construct(
        AccessInterceptorValueHolderFactory $proxyFactory,
        Instantiator $instantiator,
        ServicePoolContainer $servicePoolContainer,
        ProxyDirectoryHandler $proxyDirHandler,
        Locking $limitLocking,
        Locking $newInstanceLocking
    ) {
        $this->proxyFactory = $proxyFactory;
        $this->instantiator = $instantiator;
        $this->servicePoolContainer = $servicePoolContainer;
        $this->proxyDirHandler = $proxyDirHandler;
        $this->limitLocking = $limitLocking;
        $this->newInstanceLocking = $newInstanceLocking;
    }

    /**
     * @template RealObjectType of object
     *
     * @param RealObjectType $instance
     * @param class-string   $wrappedSvcClass
     * @param array<string>  $factoryMethods
     *
     * @return AccessInterceptorInterface<RealObjectType>&AccessInterceptorValueHolderInterface<RealObjectType>&RealObjectType&ValueHolderInterface<RealObjectType>
     */
    public function newInstance(
        object $instance,
        array $factoryMethods,
        string $wrappedSvcClass,
        int $instancesLimit
    ): object {
        $this->proxyDirHandler->ensureProxyDirExists();
        /**
         * @var array<string, callable(
         *  AccessInterceptorInterface<RealObjectType>&RealObjectType=,
         *  RealObjectType=,
         *  string=,
         *  array<string, mixed>=,
         *  bool=
         * ): mixed> $prefixInterceptors
         */
        $prefixInterceptors = [];
        $servicePoolContainer = $this->servicePoolContainer;
        $instantiator = $this->instantiator;

        if (empty($factoryMethods)) {
            throw new \RuntimeException(sprintf('Factory methods missing for class %s', get_class($instance)));
        }

        foreach ($factoryMethods as $factoryMethod) {
            if (!method_exists($instance, $factoryMethod)) {
                throw new \RuntimeException(sprintf('Missing method %s in class %s', $factoryMethod, get_class($instance)));
            }

            $lockingKey = sprintf('%s::%s', get_class($instance), $factoryMethod);
            /**
             * @var callable(
             *  AccessInterceptorInterface<RealObjectType>&RealObjectType=,
             *  RealObjectType=,
             *  string=,
             *  array<string, mixed>=,
             *  bool=
             * ): mixed $interceptor
             */
            $interceptor = function (
                object $proxy,
                object $instance,
                string $method,
                array $params,
                bool &$returnEarly
            ) use ($servicePoolContainer, $instantiator, $wrappedSvcClass, $lockingKey, $instancesLimit) {
                $returnEarly = true;
                $factoryInstantiator = function () use ($instance, $method, $params, $lockingKey): object {
                    $lock = $this->newInstanceLocking->acquire($lockingKey);

                    try {
                        $service = $instance->{$method}(...array_values($params));
                    } finally {
                        $lock->release();
                    }

                    return $service;
                };
                // currently a separate service pool is used for each factory method of the factory, which may
                // mess with the instances limit when same service instance is being created
                // this might need refactoring later...
                // unique locking key for each managed instance of the new service pool
                $limitLockingKey = sprintf('%s::limit::%s', $lockingKey, uniqid());
                $servicePool = new UnmanagedFactoryServicePool(
                    $factoryInstantiator,
                    $limitLockingKey,
                    $this->limitLocking,
                    $instancesLimit
                );
                $servicePoolContainer->addPool($servicePool);

                return $instantiator->newInstance($servicePool, $wrappedSvcClass);
            };
            $prefixInterceptors[$factoryMethod] = $interceptor;
        }

        return $this->proxyFactory->createProxy($instance, $prefixInterceptors);
    }
}

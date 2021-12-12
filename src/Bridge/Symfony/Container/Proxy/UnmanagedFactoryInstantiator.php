<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Symfony\Container\Proxy;

use K911\Swoole\Bridge\Symfony\Container\ServicePool\ServicePoolContainer;
use K911\Swoole\Bridge\Symfony\Container\ServicePool\UnmanagedFactoryServicePool;
use K911\Swoole\Component\Locking\FirstTimeOnlyLocking;
use K911\Swoole\Component\Locking\Locking;
use ProxyManager\Factory\AccessInterceptorValueHolderFactory;
use ProxyManager\Proxy\AccessInterceptorInterface;
use ProxyManager\Proxy\AccessInterceptorValueHolderInterface;
use ProxyManager\Proxy\ValueHolderInterface;
use RuntimeException;

final class UnmanagedFactoryInstantiator
{
    private AccessInterceptorValueHolderFactory $proxyFactory;

    private Instantiator $instantiator;

    private ServicePoolContainer $servicePoolContainer;

    private ProxyDirectoryHandler $proxyDirHandler;

    private static ?Locking $locking = null;

    public function __construct(
        AccessInterceptorValueHolderFactory $proxyFactory,
        Instantiator $instantiator,
        ServicePoolContainer $servicePoolContainer,
        ProxyDirectoryHandler $proxyDirHandler
    ) {
        $this->proxyFactory = $proxyFactory;
        $this->instantiator = $instantiator;
        $this->servicePoolContainer = $servicePoolContainer;
        $this->proxyDirHandler = $proxyDirHandler;

        if (null === self::$locking) {
            self::$locking = FirstTimeOnlyLocking::init();
        }
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
    public function newInstance(object $instance, array $factoryMethods, string $wrappedSvcClass): object
    {
        $locking = self::$locking;
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
            throw new RuntimeException(sprintf('Factory methods missing for class %s', get_class($instance)));
        }

        foreach ($factoryMethods as $factoryMethod) {
            if (!method_exists($instance, $factoryMethod)) {
                throw new RuntimeException(sprintf('Missing method %s in class %s', $factoryMethod, get_class($instance)));
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
            ) use ($servicePoolContainer, $instantiator, $wrappedSvcClass, $locking, $lockingKey) {
                $returnEarly = true;
                $factoryInstantiator = function () use ($instance, $method, $params, $locking, $lockingKey): object {
                    $lock = $locking->acquire($lockingKey);

                    try {
                        $service = $instance->{$method}(...array_values($params));
                    } finally {
                        $lock->release();
                    }

                    return $service;
                };
                $servicePool = new UnmanagedFactoryServicePool($factoryInstantiator);
                $servicePoolContainer->addPool($servicePool);

                return $instantiator->newInstance($servicePool, $wrappedSvcClass);
            };
            $prefixInterceptors[$factoryMethod] = $interceptor;
        }

        return $this->proxyFactory->createProxy($instance, $prefixInterceptors);
    }
}

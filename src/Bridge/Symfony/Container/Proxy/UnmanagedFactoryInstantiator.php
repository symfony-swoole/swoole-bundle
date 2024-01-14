<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy;

use ProxyManager\Factory\AccessInterceptorValueHolderFactory;
use ProxyManager\Proxy\AccessInterceptorInterface;
use ProxyManager\Proxy\AccessInterceptorValueHolderInterface;
use ProxyManager\Proxy\ValueHolderInterface;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Resetter;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\ServicePool\ServicePoolContainer;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\ServicePool\UnmanagedFactoryServicePool;
use SwooleBundle\SwooleBundle\Component\Locking\MutexFactory;

final class UnmanagedFactoryInstantiator
{
    public function __construct(
        private readonly AccessInterceptorValueHolderFactory $proxyFactory,
        private Instantiator $instantiator,
        private ServicePoolContainer $servicePoolContainer,
        private readonly MutexFactory $limitLocking,
        private readonly MutexFactory $newInstanceLocking
    ) {
    }

    /**
     * @template RealObjectType of object
     *
     * @param RealObjectType $instance
     * @param array<array{
     *     factoryMethod: string,
     *     returnType: class-string,
     *     limit?: int,
     *     resetter?: Resetter
     * }> $factoryConfigs
     *
     * @return AccessInterceptorInterface<RealObjectType>&AccessInterceptorValueHolderInterface<RealObjectType>&RealObjectType&ValueHolderInterface<RealObjectType>
     */
    public function newInstance(
        object $instance,
        array $factoryConfigs,
        int $globalInstancesLimit
    ): object {
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

        if (empty($factoryConfigs)) {
            throw new \RuntimeException(sprintf('Factory methods missing for class %s', $instance::class));
        }

        foreach ($factoryConfigs as $factoryConfig) {
            $factoryMethod = $factoryConfig['factoryMethod'];

            if (!method_exists($instance, $factoryMethod)) {
                throw new \RuntimeException(sprintf('Missing method %s in class %s', $factoryMethod, $instance::class));
            }

            $mutex = $this->newInstanceLocking->newMutex();
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
            ) use ($servicePoolContainer, $instantiator, $factoryConfig, $mutex, $globalInstancesLimit) {
                $returnEarly = true;
                $factoryInstantiator = function () use ($instance, $method, $params, $mutex): object {
                    $mutex->acquire();

                    try {
                        $service = $instance->{$method}(...array_values($params));
                    } finally {
                        $mutex->release();
                    }

                    return $service;
                };
                // currently a separate service pool is used for each factory method of the factory, which may
                // mess with the instances limit when same service instance is being created
                // this might need refactoring later...
                // unique locking key for each managed instance of the new service pool
                $limitMutex = $this->limitLocking->newMutex();
                $instancesLimit = $factoryConfig['limit'] ?? $globalInstancesLimit;
                $resetter = $factoryConfig['resetter'] ?? null;
                $servicePool = new UnmanagedFactoryServicePool(
                    $factoryInstantiator,
                    $limitMutex,
                    $instancesLimit,
                    $resetter
                );
                $servicePoolContainer->addPool($servicePool);

                return $instantiator->newInstance($servicePool, $factoryConfig['returnType']);
            };

            $prefixInterceptors[$factoryMethod] = $interceptor;
        }

        return $this->proxyFactory->createProxy($instance, $prefixInterceptors);
    }
}

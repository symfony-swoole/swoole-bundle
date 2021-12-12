<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Symfony\Container\Proxy;

use Closure;
use K911\Swoole\Bridge\Symfony\Container\ServicePool\ServicePool;
use ProxyManager\Proxy\LazyLoadingInterface;
use ProxyManager\Proxy\ValueHolderInterface;
use ProxyManager\Proxy\VirtualProxyInterface;
use Symfony\Bridge\ProxyManager\LazyProxy\Instantiator\LazyLoadingValueHolderFactory;

final class Instantiator
{
    private LazyLoadingValueHolderFactory $proxyFactory;

    private ProxyDirectoryHandler $proxyDirHandler;

    public function __construct(LazyLoadingValueHolderFactory $proxyFactory, ProxyDirectoryHandler $proxyDirHandler)
    {
        $this->proxyFactory = $proxyFactory;
        $this->proxyDirHandler = $proxyDirHandler;
    }

    /**
     * @template RealObjectType of object
     *
     * @param class-string<RealObjectType> $wrappedSvcClass
     *
     * @return RealObjectType
     */
    public function newInstance(ServicePool $servicePool, string $wrappedSvcClass): object
    {
        $this->proxyDirHandler->ensureProxyDirExists();

        /**
         * @var Closure(
         *   RealObjectType|null=,
         *   ValueHolderInterface<RealObjectType>&VirtualProxyInterface&RealObjectType=,
         *   string=,
         *   array<string, mixed>=,
         *   Closure|null=
         *  ):bool $initializer
         */
        $initializer = function (
            &$wrappedObject,
            LazyLoadingInterface $proxy,
            $method,
            array $parameters,
            &$initializer
        ) use ($servicePool) {
            // $initializer   = null; // do not disable initialization
            $wrappedObject = $servicePool->get(); // fill your object with values here

            return true; // confirm that initialization occurred correctly
        };

        return $this->proxyFactory->createProxy($wrappedSvcClass, $initializer);
    }
}

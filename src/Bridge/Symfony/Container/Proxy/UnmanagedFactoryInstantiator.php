<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy;

use ProxyManager\Factory\AccessInterceptorValueHolderFactory;
use ProxyManager\Proxy\AccessInterceptorInterface;
use ProxyManager\Proxy\AccessInterceptorValueHolderInterface;
use ProxyManager\Proxy\ValueHolderInterface;
use RuntimeException;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Initializer;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Resetter;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\ServicePool\ServicePoolContainer;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\ServicePool\ServicePoolEntry;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\ServicePool\UnmanagedFactoryServicePool;
use SwooleBundle\SwooleBundle\Common\Adapter\Swoole;
use SwooleBundle\SwooleBundle\Component\Locking\Mutex;
use SwooleBundle\SwooleBundle\Component\Locking\MutexFactory;

final readonly class UnmanagedFactoryInstantiator
{
    /**
     * Held for the life of the worker, and shared by every call below.
     *
     * Generating a proxy class is a once-per-class piece of work that both proxy factories memoize on
     * themselves - ProxyManager on AbstractBaseFactory::$checkedClasses, this bundle's Generator on its
     * own - and a memo written from two coroutines is a memo written twice. It is also file I/O, which
     * is a suspension point, so a coroutine part-way through generating a class is suspended exactly
     * where the next one arrives to generate the same one.
     *
     * The container has had that covered from the start: BlockingContainer serialises the first
     * instantiation of any service it is asked for, for this reason. What it cannot cover is a service
     * first reached some other way - Symfony's ServiceLocator, which every messenger handler and
     * controller subscriber is resolved through, implements get() itself and never enters
     * Container::get(). An unmanaged factory reached that way arrives here with nothing holding
     * anything, which is how two consumers sending mail at once first showed this up.
     *
     * Recursive by owner, so a coroutine already inside the lock passes straight back through it.
     * Outside a coroutine - the master, a console command - the mutex is a no-op.
     */
    private Mutex $instantiation;

    public function __construct(
        private AccessInterceptorValueHolderFactory $proxyFactory,
        private Instantiator $instantiator,
        private ServicePoolContainer $servicePoolContainer,
        private MutexFactory $limitLocking,
        private MutexFactory $newInstanceLocking,
        private Swoole $swoole,
        MutexFactory $instantiationLocking,
    ) {
        $this->instantiation = $instantiationLocking->newMutex();
    }

    /**
     * @template RealObjectType of object
     * @param RealObjectType $instance
     * @param array<array{
     *     factoryMethod: string,
     *     returnType: class-string,
     *     limit?: int,
     *     resetter?: Resetter,
     *     initializer?: Initializer
     * }> $factoryConfigs
     * @return AccessInterceptorInterface<RealObjectType>&AccessInterceptorValueHolderInterface<RealObjectType>&RealObjectType&ValueHolderInterface<RealObjectType>
     */
    public function newInstance(object $instance, array $factoryConfigs, int $globalInstancesLimit): object
    {
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
        $swoole = $this->swoole;

        if (empty($factoryConfigs)) {
            throw new RuntimeException(sprintf('Factory methods missing for class %s', $instance::class));
        }

        foreach ($factoryConfigs as $factoryConfig) {
            $factoryMethod = $factoryConfig['factoryMethod'];

            if (!method_exists($instance, $factoryMethod)) {
                throw new RuntimeException(sprintf('Missing method %s in class %s', $factoryMethod, $instance::class));
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
             * @phpstan-ignore varTag.nativeType
             */
            $interceptor = function (
                object $proxy,
                object $instance,
                string $method,
                array $params,
                bool &$returnEarly, // phpcs:ignore
            ) use ($servicePoolContainer, $instantiator, $factoryConfig, $mutex, $globalInstancesLimit, $swoole) {
                $returnEarly = true;
                $factoryInstantiator = static function () use ($instance, $method, $params, $mutex): object {
                    $mutex->acquire();

                    try {
                        $service = $instance->{$method}(...array_values($params));
                    } finally {
                        $mutex->release();
                    }

                    return $service;
                };

                // Everything below runs on every call to the factory method, and the last line of it
                // generates the pool's proxy class the first time each returnType is seen.
                $this->instantiation->acquire();

                try {
                    // currently a separate service pool is used for each factory method of the factory, which may
                    // mess with the instances limit when same service instance is being created
                    // this might need refactoring later...
                    // unique locking key for each managed instance of the new service pool
                    $limitMutex = $this->limitLocking->newMutex();
                    $instancesLimit = $factoryConfig['limit'] ?? $globalInstancesLimit;
                    $resetter = $factoryConfig['resetter'] ?? null;
                    $initializer = $factoryConfig['initializer'] ?? null;
                    $servicePool = new UnmanagedFactoryServicePool(
                        $factoryInstantiator,
                        $swoole,
                        $limitMutex,
                        $instancesLimit,
                        $initializer,
                    );
                    $servicePoolContainer->addPoolEntry(new ServicePoolEntry($servicePool, $resetter));

                    return $instantiator->newInstance($servicePool, $factoryConfig['returnType']);
                } finally {
                    $this->instantiation->release();
                }
            };

            $prefixInterceptors[$factoryMethod] = $interceptor;
        }

        // Generates a proxy class for the factory the first time this worker wraps one of its kind.
        $this->instantiation->acquire();

        try {
            return $this->proxyFactory->createProxy($instance, $prefixInterceptors);
        } finally {
            $this->instantiation->release();
        }
    }
}

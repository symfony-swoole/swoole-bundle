<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy;

use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\ServicePool\ServicePool;

final readonly class Instantiator
{
    public function __construct(private Generator $proxyGenerator) {}

    /**
     * @template RealObjectType of object
     * @param ServicePool<RealObjectType> $servicePool
     * @param class-string<RealObjectType> $wrappedSvcClass
     * @return ContextualProxy<RealObjectType>&RealObjectType
     */
    public function newInstance(ServicePool $servicePool, string $wrappedSvcClass): object
    {
        return $this->proxyGenerator->createProxy($servicePool, $wrappedSvcClass);
    }
}

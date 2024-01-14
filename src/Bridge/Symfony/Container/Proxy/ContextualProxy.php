<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy;

use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\ServicePool\ServicePool;

/**
 * @template RealObjectType of object
 */
interface ContextualProxy
{
    /**
     * @return ServicePool<RealObjectType>
     */
    public function getServicePool(): ServicePool;

    /**
     * @param ServicePool<RealObjectType> $servicePool
     *
     * @return ContextualProxy<RealObjectType>&RealObjectType
     */
    public static function staticProxyConstructor(ServicePool $servicePool): object;
}

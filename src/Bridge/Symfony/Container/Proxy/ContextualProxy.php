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
     * Returns the real instance the proxy currently forwards to, assigning one from the pool to the
     * running coroutine if it does not hold one yet - exactly what any forwarded method call does.
     *
     * Needed by collaborators that have to tell the pooled instances apart instead of seeing them all
     * as the single, shared proxy object.
     *
     * @return RealObjectType
     */
    public function getContextualObject(): object;

    /**
     * @param ServicePool<RealObjectType> $servicePool
     * @return ContextualProxy<RealObjectType>&RealObjectType
     */
    public static function staticProxyConstructor(ServicePool $servicePool): object;
}

<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Grpc\Interceptor;

use SwooleBundle\SwooleBundle\Server\Grpc\Context\Context;

/**
 * Interface for gRPC interceptors.
 *
 * Interceptors provide hooks for cross-cutting concerns at the method invocation level,
 * such as logging, caching, authentication, and metrics collection.
 */
interface Interceptor
{
    /**
     * Intercept a gRPC method call.
     *
     * @param Context $context The gRPC context
     * @param callable $next The next interceptor or the actual method call
     * @return Context The modified context
     */
    public function intercept(Context $context, callable $next): Context;

    /**
     * Get the priority of this interceptor (higher values execute first).
     *
     * @return int Priority value (default: 0)
     */
    public function getPriority(): int;
}

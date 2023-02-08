<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Tideways\Apm;

use K911\Swoole\Server\Middleware\MiddlewareFactory;

final class TidewaysMiddlewareFactory implements MiddlewareFactory
{
    public function __construct(private RequestProfiler $profiler)
    {
    }

    public function createMiddleware(callable $nextMiddleware): callable
    {
        return new ProfilingMiddleware($nextMiddleware, $this->profiler);
    }
}

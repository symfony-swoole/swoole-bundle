<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Tideways\Apm;

use SwooleBundle\SwooleBundle\Server\Middleware\MiddlewareFactory;

final class TidewaysMiddlewareFactory implements MiddlewareFactory
{
    public function __construct(private readonly RequestProfiler $profiler) {}

    public function createMiddleware(callable $nextMiddleware): callable
    {
        return new ProfilingMiddleware($nextMiddleware, $this->profiler);
    }
}

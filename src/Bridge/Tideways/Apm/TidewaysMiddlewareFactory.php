<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Tideways\Apm;

use SwooleBundle\SwooleBundle\Server\Middleware\MiddlewareFactory;

final readonly class TidewaysMiddlewareFactory implements MiddlewareFactory
{
    public function __construct(private RequestProfiler $profiler) {}

    public function createMiddleware(callable $nextMiddleware): callable
    {
        return new ProfilingMiddleware($nextMiddleware, $this->profiler);
    }
}

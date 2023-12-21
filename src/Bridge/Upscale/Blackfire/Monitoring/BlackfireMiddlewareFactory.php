<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Upscale\Blackfire\Monitoring;

use K911\Swoole\Server\Middleware\MiddlewareFactory;

final class BlackfireMiddlewareFactory implements MiddlewareFactory
{
    public function __construct(private readonly RequestMonitoring $monitoring)
    {
    }

    public function createMiddleware(callable $nextMiddleware): callable
    {
        return new MonitoringMiddleware($nextMiddleware, $this->monitoring);
    }
}

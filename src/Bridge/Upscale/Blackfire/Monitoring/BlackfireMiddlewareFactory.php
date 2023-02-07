<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Upscale\Blackfire\Monitoring;

use K911\Swoole\Server\Middleware\MiddlewareFactory;

final class BlackfireMiddlewareFactory implements MiddlewareFactory
{
    private RequestMonitoring $monitoring;

    public function __construct(RequestMonitoring $monitoring)
    {
        $this->monitoring = $monitoring;
    }

    public function createMiddleware(callable $nextMiddleware): callable
    {
        return new MonitoringMiddleware($nextMiddleware, $this->monitoring);
    }
}

<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Upscale\Blackfire\Monitoring;

use SwooleBundle\SwooleBundle\Server\Middleware\MiddlewareFactory;

final readonly class BlackfireMiddlewareFactory implements MiddlewareFactory
{
    public function __construct(private RequestMonitoring $monitoring) {}

    public function createMiddleware(callable $nextMiddleware): callable
    {
        return new MonitoringMiddleware($nextMiddleware, $this->monitoring);
    }
}

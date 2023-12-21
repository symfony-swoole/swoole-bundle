<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Upscale\Blackfire\Monitoring;

use K911\Swoole\Server\Middleware\MiddlewareInjector;
use Swoole\Http\Server;

final class Apm
{
    public function __construct(
        private readonly MiddlewareInjector $injector,
        private readonly BlackfireMiddlewareFactory $middlewareFactory
    ) {
    }

    /**
     * Install monitoring instrumentation.
     */
    public function instrument(Server $server): void
    {
        $this->injector->injectMiddlevare($server, $this->middlewareFactory);
    }
}

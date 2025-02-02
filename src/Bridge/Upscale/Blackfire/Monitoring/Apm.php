<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Upscale\Blackfire\Monitoring;

use Swoole\Http\Server;
use SwooleBundle\SwooleBundle\Server\Middleware\MiddlewareInjector;

final readonly class Apm
{
    public function __construct(
        private MiddlewareInjector $injector,
        private BlackfireMiddlewareFactory $middlewareFactory,
    ) {}

    /**
     * Install monitoring instrumentation.
     */
    public function instrument(Server $server): void
    {
        $this->injector->injectMiddlevare($server, $this->middlewareFactory);
    }
}

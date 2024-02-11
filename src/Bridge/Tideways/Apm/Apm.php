<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Tideways\Apm;

use Swoole\Http\Server;
use SwooleBundle\SwooleBundle\Server\Middleware\MiddlewareInjector;

final class Apm
{
    public function __construct(
        private readonly MiddlewareInjector $injector,
        private readonly TidewaysMiddlewareFactory $middlewareFactory,
    ) {}

    /**
     * Install monitoring instrumentation.
     */
    public function instrument(Server $server): void
    {
        $this->injector->injectMiddlevare($server, $this->middlewareFactory);
    }
}

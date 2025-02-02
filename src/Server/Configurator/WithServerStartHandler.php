<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Configurator;

use Swoole\Http\Server;
use SwooleBundle\SwooleBundle\Server\HttpServerConfiguration;
use SwooleBundle\SwooleBundle\Server\LifecycleHandler\ServerStartHandler;

final readonly class WithServerStartHandler implements Configurator
{
    public function __construct(
        private ServerStartHandler $handler,
        private HttpServerConfiguration $configuration,
    ) {}

    public function configure(Server $server): void
    {
        // see: https://github.com/swoole/swoole-src/blob/077c2dfe84d9f2c6d47a4e105f41423421dd4c43/src/server/reactor_process.cc#L181
        if ($this->configuration->isReactorRunningMode()) {
            return;
        }

        $server->on('start', $this->handler->handle(...));
    }
}

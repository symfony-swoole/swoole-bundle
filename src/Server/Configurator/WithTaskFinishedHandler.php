<?php

declare(strict_types=1);

namespace K911\Swoole\Server\Configurator;

use K911\Swoole\Server\HttpServerConfiguration;
use K911\Swoole\Server\TaskHandler\TaskFinishedHandlerInterface;
use Swoole\Http\Server;

final class WithTaskFinishedHandler implements ConfiguratorInterface
{
    public function __construct(
        private TaskFinishedHandlerInterface $handler,
        private HttpServerConfiguration $configuration
    ) {
    }

    public function configure(Server $server): void
    {
        if ($this->configuration->getTaskWorkerCount() > 0) {
            $server->on('finish', [$this->handler, 'handle']);
        }
    }
}

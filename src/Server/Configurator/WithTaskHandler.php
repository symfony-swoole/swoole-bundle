<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Configurator;

use Swoole\Http\Server;
use SwooleBundle\SwooleBundle\Server\HttpServerConfiguration;
use SwooleBundle\SwooleBundle\Server\TaskHandler\TaskHandlerInterface;

final class WithTaskHandler implements ConfiguratorInterface
{
    public function __construct(
        private readonly TaskHandlerInterface $handler,
        private readonly HttpServerConfiguration $configuration
    ) {
    }

    public function configure(Server $server): void
    {
        if ($this->configuration->getTaskWorkerCount() > 0) {
            $server->on('task', [$this->handler, 'handle']);
        }
    }
}

<?php

declare(strict_types=1);

namespace K911\Swoole\Server\Configurator;

use K911\Swoole\Server\HttpServerConfiguration;
use K911\Swoole\Server\TaskHandler\TaskHandlerInterface;
use Swoole\Http\Server;

final class WithTaskHandler implements ConfiguratorInterface
{
    public function __construct(
        private TaskHandlerInterface $handler,
        private HttpServerConfiguration $configuration
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function configure(Server $server): void
    {
        if ($this->configuration->getTaskWorkerCount() > 0) {
            $server->on('task', [$this->handler, 'handle']);
        }
    }
}

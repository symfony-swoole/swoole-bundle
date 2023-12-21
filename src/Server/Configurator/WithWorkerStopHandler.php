<?php

declare(strict_types=1);

namespace K911\Swoole\Server\Configurator;

use K911\Swoole\Server\WorkerHandler\WorkerStopHandlerInterface;
use Swoole\Http\Server;

final class WithWorkerStopHandler implements ConfiguratorInterface
{
    public function __construct(private readonly WorkerStopHandlerInterface $handler)
    {
    }

    public function configure(Server $server): void
    {
        $server->on('WorkerStop', [$this->handler, 'handle']);
    }
}

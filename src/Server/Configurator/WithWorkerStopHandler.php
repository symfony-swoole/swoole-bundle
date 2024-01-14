<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Configurator;

use Swoole\Http\Server;
use SwooleBundle\SwooleBundle\Server\WorkerHandler\WorkerStopHandlerInterface;

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

<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Configurator;

use Swoole\Http\Server;
use SwooleBundle\SwooleBundle\Server\WorkerHandler\WorkerStopHandler;

final class WithWorkerStopHandler implements Configurator
{
    public function __construct(private readonly WorkerStopHandler $handler) {}

    public function configure(Server $server): void
    {
        $server->on('WorkerStop', [$this->handler, 'handle']);
    }
}

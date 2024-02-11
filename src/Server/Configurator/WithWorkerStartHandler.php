<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Configurator;

use Swoole\Http\Server;
use SwooleBundle\SwooleBundle\Server\WorkerHandler\WorkerStartHandler;

final class WithWorkerStartHandler implements Configurator
{
    public function __construct(private readonly WorkerStartHandler $handler) {}

    public function configure(Server $server): void
    {
        $server->on('WorkerStart', [$this->handler, 'handle']);
    }
}

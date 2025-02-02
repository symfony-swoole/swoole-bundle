<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Configurator;

use Swoole\Http\Server;
use SwooleBundle\SwooleBundle\Server\WorkerHandler\WorkerExitHandler;

final readonly class WithWorkerExitHandler implements Configurator
{
    public function __construct(private WorkerExitHandler $handler) {}

    public function configure(Server $server): void
    {
        $server->on('WorkerExit', $this->handler->handle(...));
    }
}

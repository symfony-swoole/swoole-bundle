<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Configurator;

use Swoole\Http\Server;
use SwooleBundle\SwooleBundle\Server\WorkerHandler\WorkerErrorHandler;

final readonly class WithWorkerErrorHandler implements Configurator
{
    public function __construct(private WorkerErrorHandler $handler) {}

    public function configure(Server $server): void
    {
        $server->on('WorkerError', $this->handler->handle(...));
    }
}

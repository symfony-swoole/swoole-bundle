<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Configurator;

use Swoole\Http\Server;
use SwooleBundle\SwooleBundle\Server\WorkerHandler\WorkerStartHandlerInterface;

final class WithWorkerStartHandler implements ConfiguratorInterface
{
    public function __construct(private readonly WorkerStartHandlerInterface $handler)
    {
    }

    public function configure(Server $server): void
    {
        $server->on('WorkerStart', [$this->handler, 'handle']);
    }
}

<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Configurator;

use Swoole\Http\Server;
use SwooleBundle\SwooleBundle\Server\WorkerHandler\WorkerExitHandlerInterface;

final class WithWorkerExitHandler implements ConfiguratorInterface
{
    public function __construct(private readonly WorkerExitHandlerInterface $handler)
    {
    }

    public function configure(Server $server): void
    {
        $server->on('WorkerExit', [$this->handler, 'handle']);
    }
}

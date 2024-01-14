<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Configurator;

use Swoole\Http\Server;
use SwooleBundle\SwooleBundle\Server\WorkerHandler\WorkerErrorHandlerInterface;

final class WithWorkerErrorHandler implements ConfiguratorInterface
{
    public function __construct(private readonly WorkerErrorHandlerInterface $handler)
    {
    }

    public function configure(Server $server): void
    {
        $server->on('WorkerError', [$this->handler, 'handle']);
    }
}

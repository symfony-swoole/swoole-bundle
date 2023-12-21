<?php

declare(strict_types=1);

namespace K911\Swoole\Server\Configurator;

use K911\Swoole\Server\WorkerHandler\WorkerErrorHandlerInterface;
use Swoole\Http\Server;

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

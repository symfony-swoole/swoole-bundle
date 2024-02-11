<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Configurator;

use Swoole\Http\Server;
use SwooleBundle\SwooleBundle\Server\LifecycleHandler\ServerManagerStopHandler;

final class WithServerManagerStopHandler implements Configurator
{
    public function __construct(private readonly ServerManagerStopHandler $handler) {}

    public function configure(Server $server): void
    {
        $server->on('ManagerStop', [$this->handler, 'handle']);
    }
}

<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Configurator;

use Swoole\Http\Server;
use SwooleBundle\SwooleBundle\Server\LifecycleHandler\ServerManagerStartHandler;

final class WithServerManagerStartHandler implements Configurator
{
    public function __construct(private readonly ServerManagerStartHandler $handler) {}

    public function configure(Server $server): void
    {
        $server->on('ManagerStart', [$this->handler, 'handle']);
    }
}

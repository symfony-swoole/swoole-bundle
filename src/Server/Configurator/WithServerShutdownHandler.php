<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Configurator;

use Swoole\Http\Server;
use SwooleBundle\SwooleBundle\Server\LifecycleHandler\ServerShutdownHandler;

final readonly class WithServerShutdownHandler implements Configurator
{
    public function __construct(private ServerShutdownHandler $handler) {}

    public function configure(Server $server): void
    {
        $server->on('shutdown', $this->handler->handle(...));
    }
}

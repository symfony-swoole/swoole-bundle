<?php

declare(strict_types=1);

namespace K911\Swoole\Server\Configurator;

use K911\Swoole\Server\LifecycleHandler\ServerManagerStartHandlerInterface;
use Swoole\Http\Server;

final class WithServerManagerStartHandler implements ConfiguratorInterface
{
    public function __construct(private ServerManagerStartHandlerInterface $handler)
    {
    }

    public function configure(Server $server): void
    {
        $server->on('ManagerStart', [$this->handler, 'handle']);
    }
}

<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Configurator;

use Swoole\Http\Server;
use SwooleBundle\SwooleBundle\Server\LifecycleHandler\ServerManagerStopHandlerInterface;

final class WithServerManagerStopHandler implements ConfiguratorInterface
{
    public function __construct(private readonly ServerManagerStopHandlerInterface $handler)
    {
    }

    public function configure(Server $server): void
    {
        $server->on('ManagerStop', [$this->handler, 'handle']);
    }
}

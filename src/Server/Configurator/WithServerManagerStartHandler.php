<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Configurator;

use Swoole\Http\Server;
use SwooleBundle\SwooleBundle\Server\LifecycleHandler\ServerManagerStartHandlerInterface;

final class WithServerManagerStartHandler implements ConfiguratorInterface
{
    public function __construct(private readonly ServerManagerStartHandlerInterface $handler)
    {
    }

    public function configure(Server $server): void
    {
        $server->on('ManagerStart', [$this->handler, 'handle']);
    }
}

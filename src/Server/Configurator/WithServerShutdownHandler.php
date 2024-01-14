<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Configurator;

use Swoole\Http\Server;
use SwooleBundle\SwooleBundle\Server\LifecycleHandler\ServerShutdownHandlerInterface;

final class WithServerShutdownHandler implements ConfiguratorInterface
{
    public function __construct(private readonly ServerShutdownHandlerInterface $handler)
    {
    }

    public function configure(Server $server): void
    {
        $server->on('shutdown', [$this->handler, 'handle']);
    }
}

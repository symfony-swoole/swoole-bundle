<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\LifecycleHandler;

use Swoole\Server;

final class NoOpServerStartHandler implements ServerStartHandler
{
    public function handle(Server $server): void
    {
        // noop
    }
}

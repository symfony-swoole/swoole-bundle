<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\LifecycleHandler;

use Swoole\Server;

final class NoOpServerShutdownHandler implements ServerShutdownHandler
{
    public function handle(Server $server): void
    {
        // noop
    }
}

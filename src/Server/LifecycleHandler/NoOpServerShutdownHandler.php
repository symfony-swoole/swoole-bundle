<?php

declare(strict_types=1);

namespace K911\Swoole\Server\LifecycleHandler;

use Swoole\Server;

final class NoOpServerShutdownHandler implements ServerShutdownHandlerInterface
{
    public function handle(Server $server): void
    {
        // noop
    }
}

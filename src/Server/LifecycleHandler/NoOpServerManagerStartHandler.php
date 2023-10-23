<?php

declare(strict_types=1);

namespace K911\Swoole\Server\LifecycleHandler;

use Swoole\Server;

final class NoOpServerManagerStartHandler implements ServerManagerStartHandlerInterface
{
    public function handle(Server $server): void
    {
        // noop
    }
}

<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\LifecycleHandler;

use Swoole\Server;

interface ServerManagerStartHandler
{
    /**
     * Handle "OnManagerStart" event.
     *
     * Info: Handler is executed in manager process
     */
    public function handle(Server $server): void;
}

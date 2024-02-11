<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\LifecycleHandler;

use Swoole\Server;

interface ServerStartHandler
{
    /**
     * Handle "OnStart" event.
     */
    public function handle(Server $server): void;
}

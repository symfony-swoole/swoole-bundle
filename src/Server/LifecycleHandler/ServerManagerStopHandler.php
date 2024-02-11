<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\LifecycleHandler;

use Swoole\Server;

interface ServerManagerStopHandler
{
    /**
     * Handle "OnManagerStop" event.
     *
     * Info: Handler is executed in manager process
     */
    public function handle(Server $server): void;
}

<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Runtime\HMR;

use Swoole\Server;

interface HotModuleReloader
{
    /**
     * Reload HttpServer if changes in files were detected.
     */
    public function tick(Server $server): void;
}

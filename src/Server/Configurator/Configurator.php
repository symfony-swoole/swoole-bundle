<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Configurator;

use Swoole\Http\Server;

interface Configurator
{
    public function configure(Server $server): void;
}

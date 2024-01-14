<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Server\Runtime\HMR;

use Swoole\Server;
use SwooleBundle\SwooleBundle\Server\Runtime\HMR\HotModuleReloaderInterface;

class HMRSpy implements HotModuleReloaderInterface
{
    public $tick = false;

    public function tick(Server $server): void
    {
        $this->tick = true;
    }
}

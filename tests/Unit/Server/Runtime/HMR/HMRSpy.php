<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Server\Runtime\HMR;

use Swoole\Server;
use SwooleBundle\SwooleBundle\Server\Runtime\HMR\HotModuleReloader;

final class HMRSpy implements HotModuleReloader
{
    private bool $tick = false;

    public function tick(Server $server): void
    {
        $this->tick = true;
    }

    public function ticked(): bool
    {
        return $this->tick;
    }
}

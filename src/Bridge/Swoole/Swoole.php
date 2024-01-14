<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Swoole;

use SwooleBundle\SwooleBundle\Common\Adapter\CommonSwoole;
use SwooleBundle\SwooleBundle\Common\Adapter\WaitGroup as CommonWaitGroup;

final class Swoole extends CommonSwoole
{
    public function cpuCoresCount(): int
    {
        return \swoole_cpu_num();
    }

    public function waitGroup(int $delta = 0): CommonWaitGroup
    {
        return new WaitGroup($delta);
    }
}

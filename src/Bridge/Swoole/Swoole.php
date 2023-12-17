<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Swoole;

use K911\Swoole\Common\Adapter\CommonSwoole;
use K911\Swoole\Common\Adapter\WaitGroup as CommonWaitGroup;

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

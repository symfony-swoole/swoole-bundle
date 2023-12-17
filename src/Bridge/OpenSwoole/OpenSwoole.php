<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\OpenSwoole;

use K911\Swoole\Common\Adapter\CommonSwoole;
use K911\Swoole\Common\Adapter\WaitGroup as CommonWaitGroup;
use OpenSwoole\Util;

final class OpenSwoole extends CommonSwoole
{
    public function cpuCoresCount(): int
    {
        return Util::getCPUNum();
    }

    public function waitGroup(int $delta = 0): CommonWaitGroup
    {
        return new WaitGroup($delta);
    }
}

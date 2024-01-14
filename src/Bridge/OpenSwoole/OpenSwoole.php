<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\OpenSwoole;

use OpenSwoole\Util;
use SwooleBundle\SwooleBundle\Common\Adapter\CommonSwoole;
use SwooleBundle\SwooleBundle\Common\Adapter\WaitGroup as CommonWaitGroup;

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

<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\OpenSwoole;

use OpenSwoole\Coroutine;
use OpenSwoole\Runtime;
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

    public function enableCoroutines(int $flags = SWOOLE_HOOK_ALL): void
    {
        Runtime::enableCoroutine(true, $flags);
    }

    public function disableCoroutines(): void
    {
        Runtime::enableCoroutine(false);
    }

    public function getCoroutineId(): int
    {
        return Coroutine::getCid();
    }
}

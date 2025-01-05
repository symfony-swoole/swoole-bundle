<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Swoole;

use Swoole\Coroutine;
use Swoole\Runtime;
use SwooleBundle\SwooleBundle\Common\Adapter\CommonSwoole;
use SwooleBundle\SwooleBundle\Common\Adapter\WaitGroup as CommonWaitGroup;

final class Swoole extends CommonSwoole
{
    public function cpuCoresCount(): int
    {
        return swoole_cpu_num();
    }

    public function waitGroup(int $delta = 0): CommonWaitGroup
    {
        return new WaitGroup($delta);
    }

    public function enableCoroutines(int $flags = SWOOLE_HOOK_ALL): void
    {
        Runtime::enableCoroutine($flags); /** @phpstan-ignore-line */
    }

    public function disableCoroutines(): void
    {
        Runtime::enableCoroutine(0); /** @phpstan-ignore-line */
    }

    public function getCoroutineId(): int
    {
        return Coroutine::getCid();
    }
}

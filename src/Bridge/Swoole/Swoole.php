<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Swoole;

use RuntimeException;
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

    /**
     * @return array<string, mixed>
     */
    public function getCoroutineOptions(): array
    {
        return Coroutine::getOptions();
    }

    /**
     * @return array<string, int>
     */
    public function getRunningModes(): array
    {
        return [
            'process' => SWOOLE_PROCESS,
            'reactor' => SWOOLE_BASE,
            // 'thread' => SWOOLE_THREAD,
        ];
    }

    public function enableFiberContext(): void
    {
        throw new RuntimeException(
            'Enabling fiber context is not supported for Swoole in runtime mode. '
            . 'Use swoole.enable_fiber_mock=On in swoole ini to enable the fiber context.',
        );
    }
}

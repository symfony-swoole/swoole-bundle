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

    /**
     * Plain SWOOLE_HOOK_ALL: OpenSwoole's already selects the native curl hook rather than the PHP
     * reimplementation swoole's picks, so there is nothing here to correct.
     */
    public function coroutineHookFlags(): int
    {
        return SWOOLE_HOOK_ALL;
    }

    public function enableCoroutines(?int $flags = null): void
    {
        $flags ??= $this->coroutineHookFlags();
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
        $modes = [
            'process' => SWOOLE_PROCESS,
            'reactor' => SWOOLE_BASE,
        ];

        if (defined('OPENSWOOLE_IO_URING')) {
            $modes['iouring'] = OPENSWOOLE_IO_URING;
        }

        return $modes;
    }

    public function enableFiberContext(): void
    {
        Coroutine::set(['use_fiber_context' => true]);
    }
}

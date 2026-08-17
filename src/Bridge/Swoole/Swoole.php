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

    /**
     * SWOOLE_HOOK_ALL with the native curl hook in place of the original one.
     *
     * swoole ships two curl hooks: the original reimplements curl in PHP on top of Swoole\Curl\Handler,
     * and the native one drives libcurl's own multi interface. `ALL` still selects the original, which
     * implements only part of curl's option set - CURLOPT_SHARE among the parts it does not:
     *
     *   Swoole\Curl\Exception: swoole_curl_setopt(): option[10100] is not supported
     *
     * Symfony's CurlHttpClient sets exactly that on every request, from the share handle
     * CurlClientState keeps for connection and DNS reuse, so with the original hook symfony/http-client
     * throws before it sends anything and every outbound call is a 500.
     */
    public function coroutineHookFlags(): int
    {
        return (SWOOLE_HOOK_ALL & ~SWOOLE_HOOK_CURL) | SWOOLE_HOOK_NATIVE_CURL;
    }

    public function enableCoroutines(?int $flags = null): void
    {
        $flags ??= $this->coroutineHookFlags();
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

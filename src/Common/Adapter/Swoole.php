<?php

declare(strict_types=1);

namespace K911\Swoole\Common\Adapter;

interface Swoole
{
    public function tick(int $intervalMs, callable $callbackFunction, mixed ...$params): int|bool;

    public function cpuCoresCount(): int;

    public function waitGroup(int $delta = 0): WaitGroup;
}

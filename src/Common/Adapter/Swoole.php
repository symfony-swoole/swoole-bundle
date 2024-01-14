<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Common\Adapter;

interface Swoole
{
    public function tick(int $intervalMs, callable $callbackFunction, mixed ...$params): int|bool;

    public function cpuCoresCount(): int;

    public function waitGroup(int $delta = 0): WaitGroup;
}

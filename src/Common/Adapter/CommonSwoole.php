<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Common\Adapter;

use Swoole\Timer;

abstract class CommonSwoole implements Swoole
{
    public function tick(int $intervalMs, callable $callbackFunction, mixed ...$params): int|bool
    {
        return Timer::tick($intervalMs, $callbackFunction, ...$params);
    }
}

<?php

declare(strict_types=1);

namespace K911\Swoole\Common;

use Swoole\Timer;

final class DefaultSwooleFacade implements SwooleFacade
{
    public function tick(int $intervalMs, callable $callbackFunction, mixed ...$params): int|bool
    {
        return Timer::tick($intervalMs, $callbackFunction, ...$params);
    }
}

<?php

declare(strict_types=1);

namespace K911\Swoole\Common;

interface SwooleFacade
{
    public function tick(int $intervalMs, callable $callbackFunction, mixed ...$params): int|bool;
}

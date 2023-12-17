<?php

declare(strict_types=1);

namespace K911\Swoole\Tests\Unit\Server;

use K911\Swoole\Common\Adapter\Swoole;
use K911\Swoole\Common\Adapter\WaitGroup;
use K911\Swoole\Tests\Helper\SwooleFactory;

final class SwooleSpy implements Swoole
{
    public $registeredTick = false;

    public $registeredTickTuple = [];

    public function tick(int $intervalMs, callable $callbackFunction, ...$params): int|bool
    {
        $this->registeredTick = true;
        $this->registeredTickTuple = [$intervalMs, $callbackFunction];

        return true;
    }

    public function cpuCoresCount(): int
    {
        return 1;
    }

    public function waitGroup(int $delta = 0): WaitGroup
    {
        return SwooleFactory::newInstance()->waitGroup($delta);
    }
}

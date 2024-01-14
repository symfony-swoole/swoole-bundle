<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Server;

use SwooleBundle\SwooleBundle\Common\Adapter\Swoole;
use SwooleBundle\SwooleBundle\Common\Adapter\WaitGroup;
use SwooleBundle\SwooleBundle\Tests\Helper\SwooleFactory;

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

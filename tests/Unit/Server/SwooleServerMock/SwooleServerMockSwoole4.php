<?php

declare(strict_types=1);

namespace K911\Swoole\Tests\Unit\Server\SwooleServerMock;

use K911\Swoole\Tests\Unit\Server\SwooleServerMock;

final class SwooleServerMockSwoole4 extends SwooleServerMock
{
    public function tick($ms, callable $callback)
    {
        $this->registeredTick = true;
        $this->registeredTickTuple = [$ms, $callback];

        return true;
    }
}

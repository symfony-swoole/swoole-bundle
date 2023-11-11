<?php

declare(strict_types=1);

namespace K911\Swoole\Tests\Unit\Server\SwooleServerMock;

use K911\Swoole\Tests\Unit\Server\SwooleServerMock;

final class SwooleServerMockOpenSwoole4 extends SwooleServerMock
{
    public function tick(int $ms, callable $callback, ...$params): int|bool
    {
        $this->registeredTick = true;
        $this->registeredTickTuple = [$ms, $callback];

        return true;
    }
}

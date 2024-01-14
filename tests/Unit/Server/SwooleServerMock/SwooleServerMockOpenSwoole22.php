<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Server\SwooleServerMock;

use SwooleBundle\SwooleBundle\Tests\Unit\Server\SwooleServerMock;

final class SwooleServerMockOpenSwoole22 extends SwooleServerMock
{
    public function tick(int $ms, callable $callback, ...$params): int|bool
    {
        $this->registeredTick = true;
        $this->registeredTickTuple = [$ms, $callback];

        return true;
    }
}

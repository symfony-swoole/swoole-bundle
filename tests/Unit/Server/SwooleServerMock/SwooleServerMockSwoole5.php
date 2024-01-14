<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Server\SwooleServerMock;

use SwooleBundle\SwooleBundle\Tests\Unit\Server\SwooleServerMock;

final class SwooleServerMockSwoole5 extends SwooleServerMock
{
    public function tick($ms, callable $callback, ...$params)
    {
        $this->registeredTick = true;
        $this->registeredTickTuple = [$ms, $callback];

        return true;
    }
}

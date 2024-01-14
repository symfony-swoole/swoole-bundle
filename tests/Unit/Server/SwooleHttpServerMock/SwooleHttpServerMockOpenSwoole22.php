<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Server\SwooleHttpServerMock;

use SwooleBundle\SwooleBundle\Tests\Unit\Server\SwooleHttpServerMock;

final class SwooleHttpServerMockOpenSwoole22 extends SwooleHttpServerMock
{
    public function on(string $event, callable $callback): bool
    {
        $this->registeredEvent = true;
        $this->registeredEventPair = [$event, $callback];

        return true;
    }
}

<?php

declare(strict_types=1);

namespace K911\Swoole\Tests\Unit\Server\SwooleHttpServerMock;

use K911\Swoole\Tests\Unit\Server\SwooleHttpServerMock;

final class SwooleHttpServerMockSwoole4 extends SwooleHttpServerMock
{
    public function on($event_name, callable $callback): bool
    {
        $this->registeredEvent = true;
        $this->registeredEventPair = [$event_name, $callback];

        return true;
    }
}

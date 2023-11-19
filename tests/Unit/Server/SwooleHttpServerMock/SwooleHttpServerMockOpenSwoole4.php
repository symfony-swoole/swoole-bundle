<?php

declare(strict_types=1);

namespace K911\Swoole\Tests\Unit\Server\SwooleHttpServerMock;

use K911\Swoole\Tests\Unit\Server\SwooleHttpServerMock;

final class SwooleHttpServerMockOpenSwoole4 extends SwooleHttpServerMock
{
    public function on(string $event, callable $callback): bool
    {
        $this->registeredEvent = true;
        $this->registeredEventPair = [$event, $callback];

        return true;
    }
}

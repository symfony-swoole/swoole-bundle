<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Server\SwooleHttpServerMock;

use SwooleBundle\SwooleBundle\Tests\Unit\Server\SwooleHttpServerMock;

final class SwooleHttpServerMockSwoole5 extends SwooleHttpServerMock
{
    // phpcs:disable SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingAnyTypeHint
    public function on($event_name, callable $callback): bool
    {
        $this->registeredEvent = true;
        $this->registeredEventPair = [$event_name, $callback];

        return true;
    }
}

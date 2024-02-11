<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Server;

use Swoole\Server;

final class SwooleServerMock extends Server
{
    private static ?self $instance = null;

    private function __construct(bool $taskworker)
    {
        parent::__construct('localhost', 31999);

        $this->taskworker = $taskworker;
    }

    public static function make(bool $taskworker = false): static
    {
        if (!self::$instance instanceof self) {
            self::$instance = new self($taskworker);
        }

        return self::$instance;
    }
}

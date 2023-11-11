<?php

declare(strict_types=1);

namespace K911\Swoole\Tests\Unit\Server;

use Swoole\Server;

class SwooleServerMock extends Server
{
    public $registeredTick = false;
    public $registeredTickTuple = [];
    private static $instance;

    private function __construct(bool $taskworker)
    {
        parent::__construct('localhost', 31999);
        $this->taskworker = $taskworker;
    }

    public static function make(bool $taskworker = false): static
    {
        if (!self::$instance instanceof static) {
            self::$instance = new static($taskworker);
        }

        self::$instance->clean();

        return self::$instance;
    }

    private function clean(): void
    {
        $this->registeredTick = false;
        $this->registeredTickTuple = [];
    }
}

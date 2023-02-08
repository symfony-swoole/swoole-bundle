<?php

declare(strict_types=1);

namespace K911\Swoole\Tests\Unit\Server;

use Swoole\Server;

final class SwooleServerMock extends Server
{
    public $registeredTick = false;
    public $registeredTickTuple = [];
    private static $instance;

    private function __construct(bool $taskworker)
    {
        parent::__construct('localhost', 31999);
        $this->taskworker = $taskworker;
    }

    public function tick(int $ms, callable $callback, ...$params): int|bool
    {
        $this->registeredTick = true;
        $this->registeredTickTuple = [$ms, $callback];

        return true;
    }

    public static function make(bool $taskworker = false): self
    {
        if (!self::$instance instanceof self) {
            self::$instance = new self($taskworker);
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

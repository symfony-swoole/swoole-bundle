<?php

declare(strict_types=1);

namespace K911\Swoole\Tests\Unit\Server;

use Swoole\Http\Server;

class SwooleHttpServerMock extends Server
{
    public $registeredEvent = false;
    public $registeredEventPair = [];
    private static $instance;

    private function __construct()
    {
        parent::__construct('localhost', 31999);
    }

    public static function make(): static
    {
        if (!self::$instance instanceof static) {
            self::$instance = new static();
        }

        self::$instance->clean();

        return self::$instance;
    }

    private function clean(): void
    {
        $this->registeredEvent = false;
        $this->registeredEventPair = [];
    }
}

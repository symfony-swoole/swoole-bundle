<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Server;

use Swoole\Http\Server;

abstract class SwooleHttpServerMock extends Server
{
    protected bool $registeredEvent = false;

    /**
     * @var array{0?: string, 1?: callable}
     */
    protected array $registeredEventPair = [];

    private static ?self $instance = null;

    private function __construct()
    {
        parent::__construct('localhost', 31999);
    }

    public static function make(): static
    {
        if (!self::$instance instanceof static) {
            self::$instance = new static(); /** @phpstan-ignore new.static */
        }

        self::$instance->clean();

        return self::$instance;
    }

    public function registeredEvent(): bool
    {
        return $this->registeredEvent;
    }

    /**
     * @return array{0: string, 1: callable}
     */
    public function registeredEventPair(): array
    {
        return $this->registeredEventPair;
    }

    private function clean(): void
    {
        $this->registeredEvent = false;
        $this->registeredEventPair = [];
    }
}

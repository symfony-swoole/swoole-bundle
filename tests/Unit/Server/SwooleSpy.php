<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Server;

use SwooleBundle\SwooleBundle\Common\Adapter\Swoole;
use SwooleBundle\SwooleBundle\Common\Adapter\WaitGroup;
use SwooleBundle\SwooleBundle\Tests\Helper\SwooleFactoryFactory;

final class SwooleSpy implements Swoole
{
    private bool $registeredTick = false;

    /**
     * @var array{0: int, 1: callable}|array{}
     */
    private array $registeredTickTuple = [];

    public function tick(int $intervalMs, callable $callbackFunction, mixed ...$params): int|bool
    {
        $this->registeredTick = true;
        $this->registeredTickTuple = [$intervalMs, $callbackFunction];

        return true;
    }

    public function cpuCoresCount(): int
    {
        return 1;
    }

    public function waitGroup(int $delta = 0): WaitGroup
    {
        return SwooleFactoryFactory::newInstance()->waitGroup($delta);
    }

    public function registeredTick(): bool
    {
        return $this->registeredTick;
    }

    /**
     * @return array{0: int, 1: callable}|array{}
     */
    public function registeredTickTuple(): array
    {
        return $this->registeredTickTuple;
    }

    public function enableCoroutines(int $flags = SWOOLE_HOOK_ALL): void
    {
        // not needed for tests
    }

    public function disableCoroutines(): void
    {
        // not needed for tests
    }
}

<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Server\WorkerHandler;

use RuntimeException;
use Swoole\Server;
use SwooleBundle\SwooleBundle\Server\Runtime\HMR\HotModuleReloader;

/**
 * Counts its ticks, and can stand in for a poll the timer fires again from, or one that fails.
 */
final class CountingReloader implements HotModuleReloader
{
    private int $ticks = 0;

    /**
     * @var (callable(): void)|null
     */
    private $duringNextTick;

    private bool $throwOnNextTick = false;

    public function tick(Server $server): void
    {
        ++$this->ticks;

        if ($this->duringNextTick !== null) {
            $duringTick = $this->duringNextTick;
            $this->duringNextTick = null;
            $duringTick();
        }

        if ($this->throwOnNextTick) {
            $this->throwOnNextTick = false;

            throw new RuntimeException('The poll failed.');
        }
    }

    /**
     * Calls this once, from inside the next tick: the timer firing again before that tick has returned.
     *
     * @param callable(): void $callback
     */
    public function callDuringNextTick(callable $callback): void
    {
        $this->duringNextTick = $callback;
    }

    public function throwOnNextTick(): void
    {
        $this->throwOnNextTick = true;
    }

    public function ticks(): int
    {
        return $this->ticks;
    }
}

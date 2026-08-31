<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Symfony\TaskWorker;

use ArrayObject;
use RuntimeException;
use SwooleBundle\SwooleBundle\Common\Adapter\Swoole;
use SwooleBundle\SwooleBundle\Common\Adapter\WaitGroup;

/**
 * A scheduler made of two arrays: which coroutine is running, and which spawned which.
 *
 * Everything {@see \SwooleBundle\SwooleBundle\Bridge\Symfony\TaskWorker\RunningCommand} does is decided
 * by those two answers, and real coroutines cannot give them on demand - a test would have to spawn one
 * to ask from inside it, and could then not also be somewhere else in the tree at the same time.
 */
final class CoroutineTreeSwoole implements Swoole
{
    /**
     * @var array<int, ArrayObject<array-key, mixed>>
     */
    private array $contexts = [];

    /**
     * @var array<int, int>
     */
    private array $parents = [];

    private int $running = -1;

    /**
     * @param int $parent 0 for a coroutine nothing spawned
     */
    public function spawn(int $cid, int $parent = 0): self
    {
        $this->contexts[$cid] = new ArrayObject();
        $this->parents[$cid] = $parent;

        return $this;
    }

    /**
     * @param int $cid -1 to stand outside a coroutine altogether, as a plain console process does
     */
    public function switchTo(int $cid): self
    {
        $this->running = $cid;

        return $this;
    }

    public function getCoroutineId(): int
    {
        return $this->running;
    }

    /**
     * @return ArrayObject<array-key, mixed>|null
     */
    public function getCoroutineContext(int $cid): ?ArrayObject
    {
        return $this->contexts[$cid] ?? null;
    }

    public function getParentCoroutineId(int $cid): int
    {
        return $this->parents[$cid] ?? 0;
    }

    public function tick(int $intervalMs, callable $callbackFunction, mixed ...$params): int|bool
    {
        throw new RuntimeException('Not needed for these tests.');
    }

    public function clearTimer(int $timerId): bool
    {
        throw new RuntimeException('Not needed for these tests.');
    }

    public function cpuCoresCount(): int
    {
        return 1;
    }

    public function waitGroup(int $delta = 0): WaitGroup
    {
        throw new RuntimeException('Not needed for these tests.');
    }

    public function coroutineHookFlags(): int
    {
        return 0;
    }

    public function enableCoroutines(?int $flags = null): void
    {
        // not needed for these tests
    }

    public function disableCoroutines(): void
    {
        // not needed for these tests
    }

    /**
     * @return array<string, mixed>
     */
    public function getCoroutineOptions(): array
    {
        return [];
    }

    /**
     * @return array<string, int>
     */
    public function getRunningModes(): array
    {
        return [];
    }

    public function getRunningModeFor(string $modeName): int
    {
        return -1;
    }

    public function supportsRunningMode(string $modeName): bool
    {
        return false;
    }

    public function enableFiberContext(): void
    {
        // not needed for these tests
    }
}

<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Common\Adapter;

use ArrayObject;

interface Swoole
{
    public function tick(int $intervalMs, callable $callbackFunction, mixed ...$params): int|bool;

    /**
     * Stops a repeating timer started by tick(). A worker holding one never sees its reactor run out of
     * events, so it cannot exit before max_wait_time forces it to.
     */
    public function clearTimer(int $timerId): bool;

    public function cpuCoresCount(): int;

    public function waitGroup(int $delta = 0): WaitGroup;

    /**
     * The runtime hooks this engine turns on when coroutines are enabled, and what enableCoroutines()
     * applies when it is not given any.
     */
    public function coroutineHookFlags(): int;

    public function enableCoroutines(?int $flags = null): void;

    public function disableCoroutines(): void;

    public function getCoroutineId(): int;

    /**
     * The key-value store a coroutine carries for its own use, or null when nothing is running under
     * that id - which is both a coroutine that has already returned and a process with no scheduler.
     *
     * A coroutine does not inherit its parent's store, so anything meant to be visible to the
     * coroutines below it is found by walking up {@see self::getParentCoroutineId()} rather than by
     * reading this one.
     *
     * @return ArrayObject<array-key, mixed>|null
     */
    public function getCoroutineContext(int $cid): ?ArrayObject;

    /**
     * The coroutine that spawned the given one, or 0 when it has no parent - which covers a top level
     * coroutine, a cid that has already gone, and being asked outside a coroutine at all.
     */
    public function getParentCoroutineId(int $cid): int;

    /**
     * @return array<string, mixed>
     */
    public function getCoroutineOptions(): array;

    /**
     * @return array<string, int>
     */
    public function getRunningModes(): array;

    public function getRunningModeFor(string $modeName): int;

    public function supportsRunningMode(string $modeName): bool;

    public function enableFiberContext(): void;
}

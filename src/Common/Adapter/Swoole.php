<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Common\Adapter;

interface Swoole
{
    public function tick(int $intervalMs, callable $callbackFunction, mixed ...$params): int|bool;

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

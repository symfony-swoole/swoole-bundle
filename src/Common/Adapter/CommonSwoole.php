<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Common\Adapter;

use Assert\Assertion;
use Swoole\Timer;

abstract class CommonSwoole implements Swoole
{
    public function tick(int $intervalMs, callable $callbackFunction, mixed ...$params): int|bool
    {
        return Timer::tick($intervalMs, $callbackFunction, ...$params);
    }

    public function clearTimer(int $timerId): bool
    {
        return Timer::clear($timerId);
    }

    public function getRunningModeFor(string $modeName): int
    {
        $runningModes = $this->getRunningModes();
        Assertion::inArray($modeName, array_keys($runningModes));

        return $runningModes[$modeName];
    }

    public function supportsRunningMode(string $modeName): bool
    {
        $runningModes = $this->getRunningModes();

        return array_key_exists($modeName, $runningModes);
    }

    /**
     * @return array<string, int>
     */
    abstract public function getRunningModes(): array;
}

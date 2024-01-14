<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Service;

use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\StabilityChecker;

final class SleepingCounterChecker implements StabilityChecker
{
    private bool $wasChecked = false;

    private int $checks = 0;

    public function isStable(object $service): bool
    {
        if (!$service instanceof SleepingCounter) {
            return true;
        }

        $this->wasChecked = true;
        ++$this->checks;

        return true;
    }

    public function wasChecked(): bool
    {
        return $this->wasChecked;
    }

    public function getChecks(): int
    {
        return $this->checks;
    }

    public static function getSupportedClass(): string
    {
        return SleepingCounter::class;
    }
}

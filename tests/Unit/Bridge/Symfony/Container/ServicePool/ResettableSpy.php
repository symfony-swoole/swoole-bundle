<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Symfony\Container\ServicePool;

/**
 * A pooled service that counts how many times it was reset.
 */
final class ResettableSpy
{
    private int $resetCallCount = 0;

    public function reset(): void
    {
        $this->resetCallCount++;
    }

    public function resetCallCount(): int
    {
        return $this->resetCallCount;
    }
}

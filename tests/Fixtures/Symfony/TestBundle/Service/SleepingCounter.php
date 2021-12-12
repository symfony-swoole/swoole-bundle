<?php

declare(strict_types=1);

namespace K911\Swoole\Tests\Fixtures\Symfony\TestBundle\Service;

final class SleepingCounter
{
    private int $counter = 0;

    public function sleepAndCount(): void
    {
        sleep(2);
        ++$this->counter;
    }

    public function getCounter(): int
    {
        return $this->counter;
    }
}

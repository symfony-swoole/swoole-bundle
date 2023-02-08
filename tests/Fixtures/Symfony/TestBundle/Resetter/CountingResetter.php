<?php

declare(strict_types=1);

namespace K911\Swoole\Tests\Fixtures\Symfony\TestBundle\Resetter;

use K911\Swoole\Bridge\Symfony\Container\Resetter;

final class CountingResetter implements Resetter
{
    private int $counter = 0;

    public function __construct(private Resetter $decorated)
    {
    }

    public function reset(object $service): void
    {
        ++$this->counter;
        $this->decorated->reset($service);
    }

    public function getCounter(): int
    {
        return $this->counter;
    }
}

<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Resetter;

use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Resetter;

final class CountingResetter implements Resetter
{
    private int $counter = 0;

    public function __construct(private readonly Resetter $decorated)
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

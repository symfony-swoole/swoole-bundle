<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Initializer;

use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Initializer;

final class CountingInitializer implements Initializer
{
    private int $counter = 0;

    public function __construct(private readonly Initializer $decorated) {}

    public function initialize(object $service): void
    {
        ++$this->counter;
        $this->decorated->initialize($service);
    }

    public function getCounter(): int
    {
        return $this->counter;
    }
}

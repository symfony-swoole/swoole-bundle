<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Container;

final class SimpleResetter implements Resetter
{
    public function __construct(private readonly string $resetFn)
    {
    }

    public function reset(object $service): void
    {
        $service->{$this->resetFn}();
    }
}

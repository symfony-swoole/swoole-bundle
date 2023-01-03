<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Symfony\Container;

final class SimpleResetter implements Resetter
{
    private string $resetFn;

    public function __construct(string $resetFn)
    {
        $this->resetFn = $resetFn;
    }

    public function reset(object $service): void
    {
        $service->{$this->resetFn}();
    }
}

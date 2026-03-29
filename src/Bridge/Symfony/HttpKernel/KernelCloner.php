<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\HttpKernel;

use Symfony\Component\HttpKernel\Kernel;

final class KernelCloner
{
    private bool $isBooted = false;

    public function __construct(
        private Kernel $kernel,
    ) {}

    public function clone(): Kernel
    {
        if (!$this->isBooted) {
            $this->kernel->boot();
            $this->isBooted = true;
        }

        return clone $this->kernel;
    }
}

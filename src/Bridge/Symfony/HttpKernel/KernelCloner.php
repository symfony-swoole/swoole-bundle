<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\HttpKernel;

use Symfony\Component\HttpKernel\KernelInterface;

final class KernelCloner
{
    private bool $isBooted = false;

    /**
     * The interface rather than Kernel, because only boot() and cloning are used - and because the
     * "kernel_original" service this is given is declared as KernelInterface. A concrete type here is a
     * mismatch CheckTypeDeclarationsPass reports, which makes "lint:container" fail in any environment
     * running with coroutines, even though the service always holds the concrete kernel at runtime.
     */
    public function __construct(
        private KernelInterface $kernel,
    ) {}

    public function clone(): KernelInterface
    {
        if (!$this->isBooted) {
            $this->kernel->boot();
            $this->isBooted = true;
        }

        return clone $this->kernel;
    }
}

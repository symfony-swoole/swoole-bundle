<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\HttpKernel;

use Symfony\Component\HttpKernel\KernelInterface;

final class SimpleKernelPool implements KernelPoolInterface
{
    public function __construct(private readonly KernelInterface $kernel)
    {
    }

    public function boot(): void
    {
        $this->kernel->boot();
    }

    public function get(): KernelInterface
    {
        return $this->kernel;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function return(KernelInterface $kernel): void
    {
        // no need to be implemented
    }
}

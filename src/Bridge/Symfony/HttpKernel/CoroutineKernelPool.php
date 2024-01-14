<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\HttpKernel;

use Symfony\Component\HttpKernel\KernelInterface;

final class CoroutineKernelPool implements KernelPoolInterface
{
    /**
     * @var array<KernelInterface>
     */
    private array $kernels = [];

    public function __construct(private readonly KernelInterface $kernel)
    {
    }

    public function boot(): void
    {
        $this->kernel->boot();
    }

    public function get(): KernelInterface
    {
        if (empty($this->kernels)) {
            return clone $this->kernel;
        }

        return array_shift($this->kernels);
    }

    public function return(KernelInterface $kernel): void
    {
        $this->kernels[] = $kernel;
    }
}

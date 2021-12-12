<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Symfony\HttpKernel;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\KernelInterface;

final class CoroutineKernelPool implements KernelPoolInterface
{
    private KernelInterface $kernel;

    /**
     * @var array<KernelInterface>
     */
    private array $kernels = [];

    public function __construct(KernelInterface $kernel)
    {
        $this->kernel = $kernel;
    }

    public function boot(): void
    {
        $this->kernel->boot();
        // this will boot the http kernel before the start of swoole web workers, whic means that
        // routers etc. will be initialized before getting into coroutine context
        // without this there are concurrency problems while loading the application
        $this->kernel->handle(new Request());
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

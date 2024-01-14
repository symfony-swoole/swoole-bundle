<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\HttpKernel;

use Symfony\Component\HttpKernel\KernelInterface;

interface KernelPoolInterface
{
    public function boot(): void;

    public function get(): KernelInterface;

    public function return(KernelInterface $kernel): void;
}

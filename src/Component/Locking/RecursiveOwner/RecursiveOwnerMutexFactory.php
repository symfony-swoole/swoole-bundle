<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Component\Locking\RecursiveOwner;

use SwooleBundle\SwooleBundle\Common\Adapter\Swoole;
use SwooleBundle\SwooleBundle\Component\Locking\MutexFactory;

final readonly class RecursiveOwnerMutexFactory implements MutexFactory
{
    public function __construct(
        private Swoole $swoole,
        private MutexFactory $wrapped,
    ) {}

    public function newMutex(): RecursiveOwnerMutex
    {
        return new RecursiveOwnerMutex($this->swoole, $this->wrapped->newMutex());
    }
}

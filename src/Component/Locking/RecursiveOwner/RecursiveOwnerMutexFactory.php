<?php

declare(strict_types=1);

namespace K911\Swoole\Component\Locking\RecursiveOwner;

use K911\Swoole\Component\Locking\MutexFactory;

class RecursiveOwnerMutexFactory implements MutexFactory
{
    public function __construct(private MutexFactory $wrapped)
    {
    }

    public function newMutex(): RecursiveOwnerMutex
    {
        return new RecursiveOwnerMutex($this->wrapped->newMutex());
    }
}

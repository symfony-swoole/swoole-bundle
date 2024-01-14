<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Component\Locking\RecursiveOwner;

use SwooleBundle\SwooleBundle\Component\Locking\MutexFactory;

class RecursiveOwnerMutexFactory implements MutexFactory
{
    public function __construct(private readonly MutexFactory $wrapped)
    {
    }

    public function newMutex(): RecursiveOwnerMutex
    {
        return new RecursiveOwnerMutex($this->wrapped->newMutex());
    }
}

<?php

declare(strict_types=1);

namespace K911\Swoole\Component\Locking\FirstTimeOnly;

use K911\Swoole\Component\Locking\MutexFactory;

final class FirstTimeOnlyMutexFactory implements MutexFactory
{
    public function __construct(private MutexFactory $wrapped)
    {
    }

    public function newMutex(): FirstTimeOnlyMutex
    {
        return new FirstTimeOnlyMutex($this->wrapped->newMutex());
    }
}

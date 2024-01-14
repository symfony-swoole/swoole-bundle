<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Component\Locking;

interface MutexFactory
{
    public function newMutex(): Mutex;
}

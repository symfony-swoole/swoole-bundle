<?php

declare(strict_types=1);

namespace K911\Swoole\Component\Locking;

interface MutexFactory
{
    public function newMutex(): Mutex;
}

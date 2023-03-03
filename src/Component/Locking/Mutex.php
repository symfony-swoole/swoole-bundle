<?php

declare(strict_types=1);

namespace K911\Swoole\Component\Locking;

interface Mutex
{
    public function acquire(): void;

    public function release(): void;

    public function isAcquired(): bool;
}

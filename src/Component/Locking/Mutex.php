<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Component\Locking;

interface Mutex
{
    public function acquire(): void;

    public function release(): void;

    public function isAcquired(): bool;
}

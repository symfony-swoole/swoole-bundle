<?php

declare(strict_types=1);

namespace K911\Swoole\Component\Locking;

interface Locking
{
    public function acquire(string $key): Lock;
}

<?php

declare(strict_types=1);

namespace K911\Swoole\Component\Locking;

interface Lock
{
    public function release(): void;
}

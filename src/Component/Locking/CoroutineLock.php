<?php

declare(strict_types=1);

namespace K911\Swoole\Component\Locking;

final class CoroutineLock implements Lock
{
    public function __construct(
        private string $key,
        private Store $store
    ) {
    }

    public function release(): void
    {
        $this->store->delete($this->key);
    }
}

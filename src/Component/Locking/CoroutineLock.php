<?php

declare(strict_types=1);

namespace K911\Swoole\Component\Locking;

final class CoroutineLock implements Lock
{
    private string $key;

    private Store $store;

    public function __construct(string $key, Store $store)
    {
        $this->key = $key;
        $this->store = $store;
    }

    public function release(): void
    {
        $this->store->delete($this->key);
    }
}

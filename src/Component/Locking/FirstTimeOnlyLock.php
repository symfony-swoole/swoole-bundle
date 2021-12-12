<?php

declare(strict_types=1);

namespace K911\Swoole\Component\Locking;

final class FirstTimeOnlyLock implements Lock
{
    public const LOCKED = 1;

    public const RELEASED = 2;

    private ?string $key;

    private ?Store $store;

    private ?Lock $wrapped;

    private function __construct(?string $key = null, ?Store $store = null, ?Lock $wrapped = null)
    {
        $this->key = $key;
        $this->store = $store;
        $this->wrapped = $wrapped;
    }

    public static function locked(string $key, Store $store, Lock $wrapped): self
    {
        return new self($key, $store, $wrapped);
    }

    public static function unlocked(): self
    {
        return new self();
    }

    public function release(): void
    {
        if (null !== $this->key) {
            $this->wrapped->release();
            $this->store->delete($this->key);
            $this->store->save($this->key, self::RELEASED);
        }
    }
}

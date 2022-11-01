<?php

declare(strict_types=1);

namespace K911\Swoole\Component\Locking;

final class CoroutineLocking implements Locking
{
    private Store $store;

    private function __construct()
    {
        $this->store = new Store();
    }

    public function acquire(string $key): Lock
    {
        $cid = \Co::getCid();

        // wait 0.01 ms if the container is already resolving the requested service
        // coroutine hook for usleep should switch context to other coroutine, while waiting
        while ($this->store->has($key) && $this->store->get($key) !== $cid) {
            usleep(10);
        }

        $this->store->save($key, $cid);

        return new CoroutineLock($key, $this->store);
    }

    public static function init(?Locking $locking = null): Locking
    {
        if (null === $locking) {
            $locking = new CoroutineLocking();
        }

        return $locking;
    }
}

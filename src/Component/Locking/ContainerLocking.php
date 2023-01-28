<?php

declare(strict_types=1);

namespace K911\Swoole\Component\Locking;

final class ContainerLocking extends CoroutineLocking
{
    private const LOCK_KEY = 'EXCLUSIVE_CONTAINER_LOCK';

    public function acquire(string $key): Lock
    {
        throw new \RuntimeException('This lock is not supposed to have variable lock keys.');
    }

    public function acquireContainerLock(): Lock
    {
        return parent::acquire(self::LOCK_KEY);
    }

    public static function init(?Locking $locking = null): Locking
    {
        if (null === $locking) {
            $locking = new ContainerLocking();
        }

        return $locking;
    }
}

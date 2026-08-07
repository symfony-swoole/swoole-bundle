<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Component\Locking\RecursiveOwner;

use Assert\Assertion;
use RuntimeException;
use SwooleBundle\SwooleBundle\Common\Adapter\Swoole;
use SwooleBundle\SwooleBundle\Component\Locking\Mutex;

final class RecursiveOwnerMutex implements Mutex
{
    private const int NO_OWNER = -2;

    private int $ownerId = self::NO_OWNER;

    private int $currentOwnerUsageCount = 0;

    public function __construct(
        private readonly Swoole $swoole,
        private readonly ?Mutex $wrapped,
    ) {}

    public function acquire(): void
    {
        $possibleOwnerId = $this->swoole->getCoroutineId();

        if ($this->isOutsideACoroutine($possibleOwnerId)) {
            return;
        }

        if ($this->canBeAcquired($possibleOwnerId)) {
            if (!$this->isAcquired()) {
                Assertion::notNull($this->wrapped);
                $this->wrapped->acquire();
                $this->ownerId = $possibleOwnerId;
            }
            ++$this->currentOwnerUsageCount;

            return;
        }

        Assertion::notNull($this->wrapped);
        $this->wrapped->acquire();
        $this->ownerId = $possibleOwnerId;
        ++$this->currentOwnerUsageCount;
    }

    public function release(): void
    {
        $possibleOwnerId = $this->swoole->getCoroutineId();

        if ($this->isOutsideACoroutine($possibleOwnerId)) {
            return;
        }

        if (!$this->isOwnedBy($possibleOwnerId)) {
            throw new RuntimeException(sprintf('Mutex cannot be released by %d.', $possibleOwnerId));
        }

        --$this->currentOwnerUsageCount;

        if ($this->currentOwnerUsageCount !== 0) {
            return;
        }

        $this->ownerId = self::NO_OWNER;
        Assertion::notNull($this->wrapped);
        $this->wrapped->release();
    }

    public function isAcquired(): bool
    {
        return $this->ownerId !== self::NO_OWNER;
    }

    private function canBeAcquired(int $possibleOwnerId): bool
    {
        return !$this->isAcquired() || $this->isOwnedBy($possibleOwnerId);
    }

    private function isOwnedBy(int $possibleOwnerId): bool
    {
        return $this->ownerId === $possibleOwnerId;
    }

    /**
     * Swoole reports -1 when nothing is running inside a coroutine - the server's own lifecycle
     * callbacks, onWorkerExit above all, are called that way.
     *
     * There is nothing to lock there, and no way to lock: the wrapped mutex waits on a Channel, and a
     * Channel outside a coroutine does not block, it throws "API must be called in the coroutine". A
     * worker exiting while another coroutine still holds this mutex would take that exception through
     * its exit handler and die mid-shutdown, leaving a server with no worker to answer anything.
     *
     * Skipping is also the honest answer rather than a workaround: without a coroutine there is no
     * scheduler running, so no other coroutine of this process can be resumed while we are in here,
     * and nothing can race us for the container.
     */
    private function isOutsideACoroutine(int $possibleOwnerId): bool
    {
        return $possibleOwnerId < 0;
    }
}

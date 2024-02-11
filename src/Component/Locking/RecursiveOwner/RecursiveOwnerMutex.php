<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Component\Locking\RecursiveOwner;

use Co;
use RuntimeException;
use SwooleBundle\SwooleBundle\Component\Locking\Mutex;

final class RecursiveOwnerMutex implements Mutex
{
    private const NO_OWNER = -2;

    private int $ownerId = self::NO_OWNER;

    private int $currentOwnerUsageCount = 0;

    public function __construct(private readonly ?Mutex $wrapped) {}

    public function acquire(): void
    {
        $possibleOwnerId = Co::getCid();

        if ($this->canBeAcquired($possibleOwnerId)) {
            if (!$this->isAcquired()) {
                $this->wrapped->acquire();
                $this->ownerId = $possibleOwnerId;
            }
            ++$this->currentOwnerUsageCount;

            return;
        }

        $this->wrapped->acquire();
        $this->ownerId = $possibleOwnerId;
        ++$this->currentOwnerUsageCount;
    }

    public function release(): void
    {
        $possibleOwnerId = Co::getCid();

        if (!$this->isOwnedBy($possibleOwnerId)) {
            throw new RuntimeException(sprintf('Mutex cannot be released by %d.', $possibleOwnerId));
        }

        --$this->currentOwnerUsageCount;

        if ($this->currentOwnerUsageCount !== 0) {
            return;
        }

        $this->ownerId = self::NO_OWNER;
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
}

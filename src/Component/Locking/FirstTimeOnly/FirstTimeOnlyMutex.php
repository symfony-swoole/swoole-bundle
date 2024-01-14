<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Component\Locking\FirstTimeOnly;

use SwooleBundle\SwooleBundle\Component\Locking\Mutex;

final class FirstTimeOnlyMutex implements Mutex
{
    private const NEW = 0;

    private const LOCKED = 1;

    private const RELEASED = 2;

    private int $lockState = self::NEW;

    private int $waitingCount = 0;

    public function __construct(private ?Mutex $wrapped)
    {
    }

    public function acquire(): void
    {
        if (self::RELEASED === $this->lockState) {
            return;
        }

        if (self::NEW === $this->lockState) {
            $this->lockState = self::LOCKED;
        } else {
            ++$this->waitingCount;
        }

        $this->wrapped->acquire();
    }

    public function release(): void
    {
        if (self::RELEASED === $this->lockState) {
            return;
        }

        for ($i = 0; $i < $this->waitingCount; ++$i) {
            $this->wrapped->release();
        }

        $this->wrapped = null;
        $this->lockState = self::RELEASED;
    }

    public function isAcquired(): bool
    {
        return null !== $this->wrapped && $this->wrapped->isAcquired();
    }
}

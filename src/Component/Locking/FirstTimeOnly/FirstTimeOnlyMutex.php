<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Component\Locking\FirstTimeOnly;

use Assert\Assertion;
use SwooleBundle\SwooleBundle\Component\Locking\Mutex;

final class FirstTimeOnlyMutex implements Mutex
{
    private const NEW = 0;

    private const LOCKED = 1;

    private const RELEASED = 2;

    private int $lockState = self::NEW;

    private int $waitingCount = 0;

    public function __construct(private ?Mutex $wrapped) {}

    public function acquire(): void
    {
        if ($this->lockState === self::RELEASED) {
            return;
        }

        if ($this->lockState === self::NEW) {
            $this->lockState = self::LOCKED;
        } else {
            ++$this->waitingCount;
        }

        Assertion::notNull($this->wrapped);
        $this->wrapped->acquire();
    }

    public function release(): void
    {
        if ($this->lockState === self::RELEASED) {
            return;
        }

        Assertion::notNull($this->wrapped);
        for ($i = 0; $i < $this->waitingCount; ++$i) {
            $this->wrapped->release();
        }

        $this->wrapped = null;
        $this->lockState = self::RELEASED;
    }

    public function isAcquired(): bool
    {
        return $this->wrapped !== null && $this->wrapped->isAcquired();
    }
}

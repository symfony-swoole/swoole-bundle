<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Component\Locking\FirstTimeOnly;

use Assert\Assertion;
use SwooleBundle\SwooleBundle\Component\Locking\Mutex;

/**
 * A mutex for what is only unsafe the first time it is done: whoever comes first holds it, whoever comes while it is
 * held waits, and once the first has let go nobody waits again - those that waited go on together, and those that
 * come later pass straight through.
 *
 * The one it wraps is taken and given back by each coroutine once: the first holds it until it lets go, and one that
 * waited passes it on as soon as it is handed it, before it goes on. So nobody gives back what another holds, and
 * every coroutine that took the wrapped mutex is the one that gave it back.
 */
final class FirstTimeOnlyMutex implements Mutex
{
    private const int NEW = 0;

    private const int LOCKED = 1;

    private const int RELEASED = 2;

    private int $lockState = self::NEW;

    public function __construct(
        private ?Mutex $wrapped,
    ) {}

    public function acquire(): void
    {
        if ($this->lockState === self::RELEASED) {
            return;
        }

        // Kept here: the first lets go of the wrapped one for good as it releases, while those that wait still
        // have it to pass on.
        $wrapped = $this->wrapped;
        Assertion::notNull($wrapped);

        if ($this->lockState === self::NEW) {
            $this->lockState = self::LOCKED;
            $wrapped->acquire();

            return;
        }

        // Handed over once the first has let go, and passed on at once: the next that waits is woken before this
        // one goes on, and none of them waits for another.
        $wrapped->acquire();
        $wrapped->release();
    }

    /**
     * Only the first one's does anything. One that waited has given the wrapped mutex back already, and one that
     * came later never took it - a release for each of them here, as there was, is one for a mutex nobody holds
     * as soon as two have waited.
     */
    public function release(): void
    {
        if ($this->lockState !== self::LOCKED) {
            return;
        }

        $wrapped = $this->wrapped;
        Assertion::notNull($wrapped);

        // Said before the wrapped one is given back: giving it back runs whoever waited, there and then.
        $this->lockState = self::RELEASED;
        $this->wrapped = null;

        $wrapped->release();
    }

    public function isAcquired(): bool
    {
        return $this->wrapped !== null && $this->wrapped->isAcquired();
    }
}

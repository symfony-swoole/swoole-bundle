<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Component\Locking\Channel;

use LogicException;
use Swoole\Coroutine\Channel;
use SwooleBundle\SwooleBundle\Component\Locking\Mutex;

final class ChannelMutex implements Mutex
{
    private bool $isAcquired = false;

    private int $waiting = 0;

    private readonly Channel $channel;

    public function __construct()
    {
        // Capacity 1 is sufficient because we only ever wake one waiter.
        $this->channel = new Channel(1);
    }

    public function acquire(): void
    {
        if (!$this->isAcquired) {
            $this->isAcquired = true;

            return;
        }

        ++$this->waiting;

        try {
            $this->channel->pop();
        } finally {
            --$this->waiting;
        }
    }

    public function release(): void
    {
        if (!$this->isAcquired) {
            throw new LogicException('Cannot release an unlocked mutex.');
        }

        if ($this->waiting === 0) {
            $this->isAcquired = false;

            return;
        }

        // Wake exactly one waiting coroutine.
        $this->channel->push(true);
    }

    public function isAcquired(): bool
    {
        return $this->isAcquired;
    }
}

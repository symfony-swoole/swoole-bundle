<?php

declare(strict_types=1);

namespace K911\Swoole\Component\Locking\Channel;

use K911\Swoole\Component\Locking\Mutex;
use Swoole\Coroutine\Channel;

final class ChannelMutex implements Mutex
{
    private bool $isAcquired = false;

    /**
     * @var array<Channel>
     */
    private array $channels = [];

    public function acquire(): void
    {
        if (!$this->isAcquired) {
            $this->isAcquired = true;

            return;
        }

        $channel = $this->provideBlockingChannel();
        $channel->pop();
        $channel->close();
    }

    public function release(): void
    {
        if (0 === count($this->channels)) {
            $this->isAcquired = false;

            return;
        }

        $nextChannel = array_shift($this->channels);
        $nextChannel->push(true);
    }

    public function isAcquired(): bool
    {
        return $this->isAcquired;
    }

    private function provideBlockingChannel(): Channel
    {
        $channel = new Channel(1);
        $this->channels[] = $channel;

        return $channel;
    }
}

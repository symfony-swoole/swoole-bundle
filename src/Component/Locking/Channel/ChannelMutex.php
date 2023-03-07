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

    /**
     * @var array<Channel>
     */
    private static array $spareChannels = [];

    public function acquire(): void
    {
        if (!$this->isAcquired) {
            $this->isAcquired = true;

            return;
        }

        $channel = $this->provideBlockingChannel();
        $channel->pop();
    }

    public function release(): void
    {
        if (0 === count($this->channels)) {
            $this->isAcquired = false;

            return;
        }

        $nextChannel = array_shift($this->channels);
        self::$spareChannels[] = $nextChannel;
        $nextChannel->push(true);
    }

    public function isAcquired(): bool
    {
        return $this->isAcquired;
    }

    private function provideBlockingChannel(): Channel
    {
        $channel = array_shift(self::$spareChannels);

        if (null === $channel) {
            $channel = new Channel(1);
        }

        $this->channels[] = $channel;

        return $channel;
    }
}

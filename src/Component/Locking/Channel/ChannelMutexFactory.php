<?php

declare(strict_types=1);

namespace K911\Swoole\Component\Locking\Channel;

use K911\Swoole\Component\Locking\MutexFactory;

class ChannelMutexFactory implements MutexFactory
{
    public function newMutex(): ChannelMutex
    {
        return new ChannelMutex();
    }
}

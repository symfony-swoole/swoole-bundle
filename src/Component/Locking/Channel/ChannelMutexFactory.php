<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Component\Locking\Channel;

use SwooleBundle\SwooleBundle\Component\Locking\MutexFactory;

class ChannelMutexFactory implements MutexFactory
{
    public function newMutex(): ChannelMutex
    {
        return new ChannelMutex();
    }
}

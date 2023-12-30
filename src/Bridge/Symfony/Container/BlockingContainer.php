<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Symfony\Container;

use K911\Swoole\Component\Locking\Channel\ChannelMutexFactory;
use K911\Swoole\Component\Locking\RecursiveOwner\RecursiveOwnerMutex;
use K911\Swoole\Component\Locking\RecursiveOwner\RecursiveOwnerMutexFactory;
use Symfony\Component\DependencyInjection\Container;

class BlockingContainer extends Container
{
    protected static RecursiveOwnerMutex $mutex;

    protected static bool $isMutexInitialized = false;

    protected static string $buildContainerNs = '';

    public function get(string $id, int $invalidBehavior = self::EXCEPTION_ON_INVALID_REFERENCE): ?object
    {
        try {
            self::$mutex->acquire();
            $service = parent::get($id, $invalidBehavior);
        } finally {
            self::$mutex->release();
        }

        return $service;
    }

    public static function setBuildContainerNs(string $buildContainerNs): void
    {
        self::$buildContainerNs = $buildContainerNs;
    }

    public static function initializeMutex(): void
    {
        if (self::$isMutexInitialized) {
            return;
        }

        self::$mutex = (new RecursiveOwnerMutexFactory(new ChannelMutexFactory()))->newMutex();
        self::$isMutexInitialized = true;
    }
}

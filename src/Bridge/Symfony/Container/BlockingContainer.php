<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Container;

use SwooleBundle\SwooleBundle\Component\Locking\Channel\ChannelMutexFactory;
use SwooleBundle\SwooleBundle\Component\Locking\RecursiveOwner\RecursiveOwnerMutex;
use SwooleBundle\SwooleBundle\Component\Locking\RecursiveOwner\RecursiveOwnerMutexFactory;
use Symfony\Component\DependencyInjection\Container;

abstract class BlockingContainer extends Container
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

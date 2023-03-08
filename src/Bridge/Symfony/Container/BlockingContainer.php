<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Symfony\Container;

use K911\Swoole\Component\Locking\Channel\ChannelMutexFactory;
use K911\Swoole\Component\Locking\RecursiveOwner\RecursiveOwnerMutex;
use K911\Swoole\Component\Locking\RecursiveOwner\RecursiveOwnerMutexFactory;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class BlockingContainer extends Container
{
    protected static RecursiveOwnerMutex $mutex;

    protected static string $buildContainerNs = '';

    /**
     * @var array<string, bool>
     */
    protected static array $nonShareableServices = [];

    public function __construct(ParameterBagInterface $parameterBag = null)
    {
        self::$mutex = (new RecursiveOwnerMutexFactory(new ChannelMutexFactory()))->newMutex();

        parent::__construct($parameterBag);
    }

    /**
     * {@inheritDoc}
     */
    public function get(string $id, int $invalidBehavior = self::EXCEPTION_ON_INVALID_REFERENCE): ?object
    {
        if (isset(static::$nonShareableServices[$id])) {
            return parent::get($id, $invalidBehavior);
        }

        $service = $this->services[$id] ?? $this->services[$id = $this->aliases[$id] ?? $id] ?? null;

        if (null !== $service) {
            return $service;
        }

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
}

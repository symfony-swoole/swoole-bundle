<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Symfony\Container;

use K911\Swoole\Component\Locking\ContainerLocking;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class BlockingContainer extends Container
{
    protected static ContainerLocking $locking;

    protected static string $buildContainerNs = '';

    public function __construct(ParameterBagInterface $parameterBag = null)
    {
        $locking = ContainerLocking::init();

        if (!$locking instanceof ContainerLocking) {
            throw new \UnexpectedValueException(sprintf('Invalid locking class: %s', $locking::class));
        }

        self::$locking = $locking;

        parent::__construct($parameterBag);
    }

    /**
     * {@inheritDoc}
     */
    public function get(string $id, int $invalidBehavior = self::EXCEPTION_ON_INVALID_REFERENCE): ?object
    {
        $lock = self::$locking->acquireContainerLock();

        try {
            $service = parent::get($id, $invalidBehavior);
        } finally {
            $lock->release();
        }

        return $service;
    }

    public static function setBuildContainerNs(string $buildContainerNs): void
    {
        self::$buildContainerNs = $buildContainerNs;
    }
}

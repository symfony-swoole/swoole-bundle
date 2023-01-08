<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Symfony\Container;

use K911\Swoole\Component\Locking\CoroutineLocking;
use K911\Swoole\Component\Locking\Locking;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class BlockingContainer extends Container
{
    protected static Locking $locking;

    protected static string $buildContainerNs = '';

    public function __construct(ParameterBagInterface $parameterBag = null)
    {
        self::$locking = CoroutineLocking::init();

        parent::__construct($parameterBag);
    }

    /**
     * {@inheritDoc}
     */
    public function get(string $id, int $invalidBehavior = self::EXCEPTION_ON_INVALID_REFERENCE): ?object
    {
        $lock = self::$locking->acquire($id);

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

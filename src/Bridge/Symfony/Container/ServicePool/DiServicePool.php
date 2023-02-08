<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Symfony\Container\ServicePool;

use K911\Swoole\Bridge\Symfony\Container\Resetter;
use K911\Swoole\Bridge\Symfony\Container\StabilityChecker;
use K911\Swoole\Component\Locking\Locking;
use Symfony\Component\DependencyInjection\Container;

final class DiServicePool extends BaseServicePool
{
    public function __construct(
        private string $wrappedServiceId,
        private Container $container,
        Locking $locking,
        int $instancesLimit = 50,
        ?Resetter $resetter = null,
        ?StabilityChecker $stabilityChecker = null
    ) {
        parent::__construct($wrappedServiceId, $locking, $instancesLimit, $resetter, $stabilityChecker);
    }

    protected function newServiceInstance(): object
    {
        return $this->container->get($this->wrappedServiceId);
    }
}

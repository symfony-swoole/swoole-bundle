<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Symfony\Container\ServicePool;

use K911\Swoole\Bridge\Symfony\Container\Resetter;
use K911\Swoole\Component\Locking\Locking;

final class UnmanagedFactoryServicePool extends BaseServicePool
{
    /**
     * @var \Closure(): object
     */
    private \Closure $instantiator;

    public function __construct(
        \Closure $instantiator,
        string $lockingKey,
        Locking $locking,
        int $instancesLimit = 50,
        ?Resetter $resetter = null
    ) {
        $this->instantiator = $instantiator;

        parent::__construct($lockingKey, $locking, $instancesLimit, $resetter);
    }

    protected function newServiceInstance(): object
    {
        $instantiator = $this->instantiator;

        return $instantiator();
    }
}

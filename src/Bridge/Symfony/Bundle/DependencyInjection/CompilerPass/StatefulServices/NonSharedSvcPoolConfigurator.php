<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices;

use K911\Swoole\Bridge\Symfony\Container\ServicePool\BaseServicePool;
use K911\Swoole\Bridge\Symfony\Container\ServicePool\ServicePoolContainer;

final class NonSharedSvcPoolConfigurator
{
    public function __construct(private ServicePoolContainer $container)
    {
    }

    /**
     * @param BaseServicePool<object> $servicePool
     */
    public function configure(BaseServicePool $servicePool): void
    {
        $this->container->addPool($servicePool);
    }
}

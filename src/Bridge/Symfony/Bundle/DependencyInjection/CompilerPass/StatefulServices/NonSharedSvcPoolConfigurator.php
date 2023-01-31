<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices;

use K911\Swoole\Bridge\Symfony\Container\ServicePool\BaseServicePool;
use K911\Swoole\Bridge\Symfony\Container\ServicePool\ServicePoolContainer;

final class NonSharedSvcPoolConfigurator
{
    private ServicePoolContainer $container;

    public function __construct(ServicePoolContainer $container)
    {
        $this->container = $container;
    }

    public function configure(BaseServicePool $servicePool): void
    {
        $this->container->addPool($servicePool);
    }
}

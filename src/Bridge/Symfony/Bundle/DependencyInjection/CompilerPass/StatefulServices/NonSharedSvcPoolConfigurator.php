<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices;

use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\ServicePool\BaseServicePool;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\ServicePool\ServicePoolContainer;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\ServicePool\ServicePoolEntry;

final readonly class NonSharedSvcPoolConfigurator
{
    public function __construct(private ServicePoolContainer $container) {}

    /**
     * @param BaseServicePool<object> $servicePool
     */
    public function configure(BaseServicePool $servicePool): void
    {
        $this->container->addPoolEntry(new ServicePoolEntry($servicePool));
    }
}

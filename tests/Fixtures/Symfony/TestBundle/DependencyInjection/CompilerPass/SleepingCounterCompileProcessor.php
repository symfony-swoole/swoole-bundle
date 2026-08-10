<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\DependencyInjection\CompilerPass;

use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices\CompileProcessor;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices\ServiceProxifier;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Service\SleepingCounter;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class SleepingCounterCompileProcessor implements CompileProcessor
{
    public function process(ContainerBuilder $container, ServiceProxifier $proxifier): void
    {
        $proxifier->proxifyService(SleepingCounter::class);
    }
}

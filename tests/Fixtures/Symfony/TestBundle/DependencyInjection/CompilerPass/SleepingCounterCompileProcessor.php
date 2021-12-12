<?php

declare(strict_types=1);

namespace K911\Swoole\Tests\Fixtures\Symfony\TestBundle\DependencyInjection\CompilerPass;

use K911\Swoole\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices\CompileProcessor;
use K911\Swoole\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices\Proxifier;
use K911\Swoole\Tests\Fixtures\Symfony\TestBundle\Service\SleepingCounter;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class SleepingCounterCompileProcessor implements CompileProcessor
{
    public function process(ContainerBuilder $container, Proxifier $proxifier): void
    {
        $proxifier->proxifyService(SleepingCounter::class);
    }
}

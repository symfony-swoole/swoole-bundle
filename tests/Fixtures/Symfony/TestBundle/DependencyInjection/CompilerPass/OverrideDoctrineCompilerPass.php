<?php

declare(strict_types=1);

namespace K911\Swoole\Tests\Fixtures\Symfony\TestBundle\DependencyInjection\CompilerPass;

use K911\Swoole\Tests\Fixtures\Symfony\TestBundle\Service\NoAutowiring\ResetCountingRegistry;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class OverrideDoctrineCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $container->setParameter('doctrine.class', ResetCountingRegistry::class);
    }
}

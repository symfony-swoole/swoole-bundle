<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass;

use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\ContainerConstants;
use SwooleBundle\SwooleBundle\Server\Configurator\WithHealthEvaluatorProcess;
use SwooleBundle\SwooleBundle\Server\Health\HealthReporter;
use SwooleBundle\SwooleBundle\Server\Health\HealthStatusTable;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class HealthCheckPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(HealthReporter::class)) {
            return;
        }

        $checkCount = count($container->findTaggedServiceIds(ContainerConstants::TAG_HEALTH_CHECK));
        if ($checkCount === 0) {
            $container->removeDefinition(WithHealthEvaluatorProcess::class);
            $container->removeDefinition(HealthStatusTable::class);
            $container->getDefinition(HealthReporter::class)->setArgument('$table', null);

            return;
        }

        $container->getDefinition(HealthStatusTable::class)->setArgument('$checkCount', $checkCount);
    }
}

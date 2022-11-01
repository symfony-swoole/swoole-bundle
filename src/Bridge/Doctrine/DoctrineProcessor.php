<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Doctrine;

use K911\Swoole\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices\CompileProcessor;
use K911\Swoole\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices\Proxifier;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class DoctrineProcessor implements CompileProcessor
{
    public function process(ContainerBuilder $container, Proxifier $proxifier): void
    {
        /** @var array<string,string> $bundles */
        $bundles = $container->getParameter('kernel.bundles');

        if (!isset($bundles['DoctrineBundle'])) {
            return;
        }

        $entityManagers = $container->getParameter('doctrine.entity_managers');

        if (!\is_array($entityManagers)) {
            throw new \UnexpectedValueException('Cannot obtain array of entity managers.');
        }

        $connectionSvcIds = [];

        foreach ($entityManagers as $emName => $emSvcId) {
            $emDef = $container->findDefinition($emSvcId);
            $proxifier->proxifyService($emSvcId);
            $this->overrideEmConfigurator($container, $emDef);
            $connRef = $emDef->getArgument(0);
            $connSvcId = (string) $connRef;
            $connectionSvcIds[$connSvcId] = $connSvcId;
            $this->decorateRepositoryFactory($container, $emName, $emSvcId);
        }

        $this->proxifyConnections($container, $proxifier, $connectionSvcIds);
        $this->fixDebugDataHolderResetter($container, $proxifier);
    }

    private function overrideEmConfigurator(ContainerBuilder $container, Definition $emDef): void
    {
        $configuratorCallback = $emDef->getConfigurator();
        /** @var Reference $configuratorRef */
        $configuratorRef = $configuratorCallback[0];
        $newConfiguratorDefSvcId = sprintf('%s.swoole_coop.blocking', (string) $configuratorRef);
        $newConfiguratorDef = new Definition(BlockingProxyFactoryOverridingManagerConfigurator::class);
        $newConfiguratorDef->setArgument(0, $configuratorRef);
        $container->setDefinition($newConfiguratorDefSvcId, $newConfiguratorDef);
        $emDef->setConfigurator([new Reference($newConfiguratorDefSvcId), 'configure']);
    }

    private function proxifyConnections(
        ContainerBuilder $container,
        Proxifier $proxifier,
        array $connectionSvcIds
    ): void {
        foreach ($connectionSvcIds as $connectionSvcId) {
            $proxifier->proxifyService($connectionSvcId);
        }
    }

    private function fixDebugDataHolderResetter(ContainerBuilder $container, Proxifier $proxifier): void
    {
        if (!$container->has('doctrine.debug_data_holder')) {
            return;
        }

        $proxifier->proxifyService('doctrine.debug_data_holder');
        $resetterDef = $container->findDefinition('services_resetter');

        if ($resetterDef->hasTag('kernel.reset')) {
            return;
        }

        /** @var IteratorArgument $resetters */
        $resetters = $resetterDef->getArgument(0);
        $resetterValues = $resetters->getValues();
        $resetterValues['doctrine.debug_data_holder'] = new Reference('doctrine.debug_data_holder');
        $resetters->setValues($resetterValues);
        $resetMethods = $resetterDef->getArgument(1);
        $resetMethods['doctrine.debug_data_holder'] = ['reset'];
        $resetterDef->setArgument(1, $resetMethods);
    }

    private function decorateRepositoryFactory(ContainerBuilder $container, string $emName, string $emSvcId): void
    {
        $configuratorSvcId = sprintf('doctrine.orm.%s_configuration', $emName);
        $configuratorDef = $container->findDefinition($configuratorSvcId);

        $newRepoFactorySvcId = sprintf('%s.%s', ServicePooledRepositoryFactory::class, $emName);
        $repoFactoryDef = new Definition(ServicePooledRepositoryFactory::class);
        $container->setDefinition($newRepoFactorySvcId, $repoFactoryDef);

        $methodCalls = $configuratorDef->getMethodCalls();

        foreach ($methodCalls as $index => $methodCall) {
            if ('setRepositoryFactory' !== $methodCall[0]) {
                continue;
            }

            $originalFactorySvcId = (string) $methodCall[1][0];
            $repoFactoryDef->setArgument(0, new Reference($originalFactorySvcId));
            $repoFactoryDef->setArgument(1, new Reference($emSvcId));
            $methodCall[1] = [0 => new Reference($newRepoFactorySvcId)];
            $methodCalls[$index] = $methodCall;

            break;
        }
        $configuratorDef->setMethodCalls($methodCalls);
    }
}

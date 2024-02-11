<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Doctrine;

use PixelFederation\DoctrineResettableEmBundle\DBAL\Connection\DBALPlatformAliveKeeper;
use SwooleBundle\SwooleBundle\Bridge\Doctrine\DBAL\ConnectionKeepAliveResetter;
use SwooleBundle\SwooleBundle\Bridge\Doctrine\ORM\EntityManagerResetter;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices\CompileProcessor;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices\Proxifier;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\ContainerConstants;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use UnexpectedValueException;

final class DoctrineProcessor implements CompileProcessor
{
    /**
     * @param array{global_limit?: int, limits?: array<string, int>} $config
     */
    public function __construct(private array $config = []) {}

    public function process(ContainerBuilder $container, Proxifier $proxifier): void
    {
        /** @var array<string,string> $bundles */
        $bundles = $container->getParameter('kernel.bundles');

        if (!isset($bundles['DoctrineBundle'])) {
            return;
        }

        $doctrineDef = $container->findDefinition('doctrine');
        $doctrineDef->addTag(ContainerConstants::TAG_SAFE_STATEFUL_SERVICE);

        $entityManagers = $container->getParameter('doctrine.entity_managers');

        if (!is_array($entityManagers)) {
            throw new UnexpectedValueException('Cannot obtain array of entity managers.');
        }

        $connectionSvcIds = $container->getParameter('doctrine.connections');

        if (!is_array($connectionSvcIds)) {
            throw new UnexpectedValueException('Cannot obtain array of doctrine connections.');
        }

        $this->createEntityManagerResetterDefinition($container);
        $this->prepareConnectionsForProxification($container, $connectionSvcIds);

        foreach ($entityManagers as $emName => $emSvcId) {
            $emDef = $container->findDefinition($emSvcId);
            $emDef->setLazy(false); // no need for another level of proxy wihich is technically lazy itself
            $tagParams = ['resetter' => EntityManagerResetter::class];
            $limit = $this->getLimitFromEntityManagerConnection($container, $emDef);

            if ($limit !== null) {
                $tagParams['limit'] = $limit;
            }

            $emDef->addTag(ContainerConstants::TAG_STATEFUL_SERVICE, $tagParams);
            $this->overrideEmConfigurator($container, $emDef);
            $this->decorateRepositoryFactory($container, $emName, $emSvcId);
        }

        $this->fixDebugDataHolderResetter($container, $proxifier);
    }

    private function createEntityManagerResetterDefinition(ContainerBuilder $container): void
    {
        $resetterDef = new Definition(EntityManagerResetter::class);
        $resetterDef->setClass(EntityManagerResetter::class);
        $container->setDefinition(EntityManagerResetter::class, $resetterDef);
    }

    private function overrideEmConfigurator(ContainerBuilder $container, Definition $emDef): void
    {
        $configuratorCallback = $emDef->getConfigurator();
        /** @var Reference $configuratorRef */
        $configuratorRef = $configuratorCallback[0];
        $newConfiguratorDefSvcId = sprintf('%s.swoole_coop.blocking', (string) $configuratorRef);
        $newConfiguratorDef = new Definition(BlockingProxyFactoryOverridingManagerConfigurator::class);
        $newConfiguratorDef->setArgument(0, $configuratorRef);
        $newConfiguratorDef->setArgument(1, new Reference('swoole_bundle.unmanaged_factory_first_time.locking'));
        $container->setDefinition($newConfiguratorDefSvcId, $newConfiguratorDef);
        $emDef->setConfigurator([new Reference($newConfiguratorDefSvcId), 'configure']);
    }

    /**
     * @param array<string,string> $connectionSvcIds
     */
    private function prepareConnectionsForProxification(ContainerBuilder $container, array $connectionSvcIds): void
    {
        $dbalAliveKeeperDef = $container->findDefinition(DBALPlatformAliveKeeper::class);
        $aliveKeepers = $dbalAliveKeeperDef->getArgument(1);
        $dbalAliveKeeperDef->setArgument(1, []);

        foreach ($connectionSvcIds as $connectionName => $connectionSvcId) {
            $limit = $this->getConnectionLimit($connectionName);

            if (!$limit) {
                $limit = $this->getGlobalConnectionLimit();
            }

            $connectionDef = $container->findDefinition($connectionSvcId);
            $tagParams = [];

            if ($limit) {
                $tagParams['limit'] = $limit;
            }

            if (isset($aliveKeepers[$connectionName])) {
                $tagParams['resetter'] = $this->tryToCreateKeepAliveResetter(
                    $container,
                    $connectionName,
                    $aliveKeepers[$connectionName]
                );
            }

            $connectionDef->addTag(ContainerConstants::TAG_STATEFUL_SERVICE, $tagParams);
        }
    }

    private function getLimitFromEntityManagerConnection(ContainerBuilder $container, Definition $emDef): ?int
    {
        /** @vat Reference $connRef */
        $connRef = $emDef->getArgument(0);
        $connDef = $container->findDefinition((string) $connRef);
        $statefulSvcTag = $connDef->getTag(ContainerConstants::TAG_STATEFUL_SERVICE);

        return $statefulSvcTag && isset($statefulSvcTag[0]['limit']) ? $statefulSvcTag[0]['limit'] : null;
    }

    private function fixDebugDataHolderResetter(ContainerBuilder $container, Proxifier $proxifier): void
    {
        if (!$container->has('doctrine.debug_data_holder')) {
            return;
        }

        $dataHolderDef = $container->findDefinition('doctrine.debug_data_holder');

        if ($dataHolderDef->hasTag('kernel.reset')) {
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
            if ($methodCall[0] !== 'setRepositoryFactory') {
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

    private function getGlobalConnectionLimit(): ?int
    {
        if (!isset($this->config['global_limit'])) {
            return null;
        }

        return $this->config['global_limit'];
    }

    private function getConnectionLimit(string $connectionName): ?int
    {
        if (!isset($this->config['limits'])) {
            return null;
        }

        if (!isset($this->config['limits'][$connectionName])) {
            return null;
        }

        return (int) $this->config['limits'][$connectionName];
    }

    private function tryToCreateKeepAliveResetter(
        ContainerBuilder $container,
        string $connectionName,
        Reference $aliveKeeperRef,
    ): string {
        $resetterSvcId = sprintf('swoole_bundle.coroutines_support.doctrine.connection_resetter.%s', $connectionName);
        $resetterDef = new Definition();
        $resetterDef->setClass(ConnectionKeepAliveResetter::class);
        $resetterDef->setArgument(0, $aliveKeeperRef);
        $resetterDef->setArgument(1, $connectionName);
        $container->setDefinition($resetterSvcId, $resetterDef);

        return $resetterSvcId;
    }
}

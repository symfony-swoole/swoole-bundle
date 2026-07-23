<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass;

use SwooleBundle\SwooleBundle\Server\Configurator\WithSwooleTableStorageConfigurator;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Adds the swoole_bundle.server_configurator tag to WithSwooleTableStorageConfigurator
 * when the Symfony Framework session is configured to use the swoole table storage factory.
 * This ensures the Configurator runs in the master process before workers are forked,
 * so all workers share the same physical shared memory.
 */
final class SwooleTableStorageConfiguratorPass implements CompilerPassInterface
{
    private const string TABLE_STORAGE_FACTORY_ALIAS = 'swoole_bundle.session.table_storage_factory';

    public function process(ContainerBuilder $container): void
    {
        /**
         * @var array<array{session?: array{
         *   storage_factory_id?: string
         * }}> $frameworkConfigs
         */
        $frameworkConfigs = $container->getExtensionConfig('framework');

        foreach ($frameworkConfigs as $config) {
            if (
                !isset($config['session']['storage_factory_id'])
                || $config['session']['storage_factory_id'] !== self::TABLE_STORAGE_FACTORY_ALIAS
            ) {
                continue;
            }

            // Table storage is in use — add the configurator tag so the Configurator
            // runs in the master process before workers are forked.
            if ($container->hasDefinition(WithSwooleTableStorageConfigurator::class)) {
                $container->getDefinition(WithSwooleTableStorageConfigurator::class)
                    ->addTag('swoole_bundle.server_configurator');
            }

            return;
        }
    }
}

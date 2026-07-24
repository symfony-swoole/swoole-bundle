<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass;

use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\ContainerConstants;
use SwooleBundle\SwooleBundle\Bridge\Symfony\HttpFoundation\Session\SessionHandlerInterfaceStorage;
use SwooleBundle\SwooleBundle\Server\Session\Storage;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Wires the bundle's Storage interface to a SessionHandlerInterfaceStorage adapter
 * when Symfony's framework.session.handler_id is configured.
 *
 * For this to take effect, the user must configure BOTH:
 *   framework.session.storage_factory_id: swoole_bundle.session.table_storage_factory
 *   framework.session.handler_id: <handler service id>
 *
 * The handler service is tagged as stateful so that StatefulServicesPass proxies it
 * per-coroutine, which is required because handlers such as PdoSessionHandler hold a
 * live PDO connection and internal transaction/prefetch state that is unsafe to share
 * across concurrent coroutines.
 */
final class SessionHandlerStorageConfiguratorPass implements CompilerPassInterface
{
    private const string SESSION_HANDLER_STORAGE_SERVICE_ID = 'swoole_bundle.session.session_handler_storage';

    public function process(ContainerBuilder $container): void
    {
        /**
         * @var array<array{session?: array{
         *   handler_id?: string|null
         * }}> $frameworkConfigs
         */
        $frameworkConfigs = $container->getExtensionConfig('framework');

        foreach ($frameworkConfigs as $config) {
            if (!isset($config['session']['handler_id'])) {
                continue;
            }

            $handlerId = $config['session']['handler_id'];

            if (!$container->hasDefinition($handlerId) && !$container->hasAlias($handlerId)) {
                // Referenced handler service does not exist; fail silently to avoid breaking
                // unrelated configurations.
                return;
            }

            $container->setDefinition(
                self::SESSION_HANDLER_STORAGE_SERVICE_ID,
                new Definition(SessionHandlerInterfaceStorage::class, [
                    new Reference($handlerId),
                ])
            );

            // Replace the default Storage alias (SwooleTableStorage) with the handler-backed adapter.
            $container->setAlias(Storage::class, self::SESSION_HANDLER_STORAGE_SERVICE_ID);

            if (!$container->hasParameter(ContainerConstants::PARAM_COROUTINES_ENABLED)) {
                return;
            }

            if (!$container->getParameter(ContainerConstants::PARAM_COROUTINES_ENABLED)) {
                return;
            }

            $this->tagHandlerAsStateful($container, $handlerId);

            return;
        }
    }

    private function tagHandlerAsStateful(ContainerBuilder $container, string $handlerId): void
    {
        if ($container->hasDefinition($handlerId)) {
            $container->getDefinition($handlerId)->addTag(ContainerConstants::TAG_STATEFUL_SERVICE);

            return;
        }

        if (!$container->hasAlias($handlerId)) {
            return;
        }

        // Aliases cannot be tagged directly; resolve to the underlying definition.
        $resolvedId = (string) $container->getAlias($handlerId);
        while ($container->hasAlias($resolvedId)) {
            $resolvedId = (string) $container->getAlias($resolvedId);
        }

        if (!$container->hasDefinition($resolvedId)) {
            return;
        }

        $container->getDefinition($resolvedId)->addTag(ContainerConstants::TAG_STATEFUL_SERVICE);
    }
}

<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass;

use SwooleBundle\SwooleBundle\Bridge\Symfony\HttpFoundation\Session\SessionCookieEventListener;
use SwooleBundle\SwooleBundle\Server\Session\StorageInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class SessionStorageListenerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasAlias('session.storage.factory')) {
            return;
        }

        $factoryAlias = $container->getAlias('session.storage.factory');

        if ('swoole_bundle.session.table_storage_factory' !== (string) $factoryAlias) {
            return;
        }

        $cookieListenerDef = new Definition(SessionCookieEventListener::class);
        $cookieListenerDef->setPublic(false);
        $cookieListenerDef->setArgument('$requestStack', new Reference('request_stack'));
        $cookieListenerDef->setArgument('$dispatcher', new Reference('event_dispatcher'));
        $cookieListenerDef->setArgument('$swooleStorage', new Reference(StorageInterface::class));
        $cookieListenerDef->setArgument('$sessionOptions', $container->getParameter('session.storage.options'));
        $cookieListenerDef->addTag('kernel.event_subscriber');
        $container->setDefinition(SessionCookieEventListener::class, $cookieListenerDef);
    }
}

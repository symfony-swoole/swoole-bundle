<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass;

use PHPUnit\Framework\TestCase;
use SessionHandlerInterface;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\{
    SessionHandlerStorageConfiguratorPass,};
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\ContainerConstants;
use SwooleBundle\SwooleBundle\Bridge\Symfony\HttpFoundation\Session\SessionHandlerInterfaceStorage;
use SwooleBundle\SwooleBundle\Server\Session\Storage;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class SessionHandlerStorageConfiguratorPassTest extends TestCase
{
    public function testProcessDoesNothingWhenHandlerIdIsNotConfigured(): void
    {
        $container = new ContainerBuilder();
        $container->prependExtensionConfig('framework', ['session' => []]);

        (new SessionHandlerStorageConfiguratorPass())->process($container);

        $this->assertFalse($container->hasDefinition('swoole_bundle.session.session_handler_storage'));
    }

    public function testProcessDoesNothingWhenHandlerServiceDoesNotExist(): void
    {
        $container = new ContainerBuilder();
        $container->prependExtensionConfig('framework', [
            'session' => [
                'handler_id' => 'my_handler',
            ],
        ]);

        (new SessionHandlerStorageConfiguratorPass())->process($container);

        $this->assertFalse($container->hasDefinition('swoole_bundle.session.session_handler_storage'));
        $this->assertFalse($container->hasAlias(Storage::class));
    }

    public function testProcessRegistersSessionHandlerStorageAndOverridesStorageAliasAndTagsHandler(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter(ContainerConstants::PARAM_COROUTINES_ENABLED, true);
        $container->prependExtensionConfig('framework', [
            'session' => [
                'handler_id' => 'my_handler',
            ],
        ]);
        $container->setDefinition('my_handler', new Definition(SessionHandlerInterface::class));

        (new SessionHandlerStorageConfiguratorPass())->process($container);

        $this->assertTrue($container->hasDefinition('swoole_bundle.session.session_handler_storage'));
        $storageDef = $container->getDefinition('swoole_bundle.session.session_handler_storage');
        $this->assertSame(SessionHandlerInterfaceStorage::class, $storageDef->getClass());

        $constructorArgs = $storageDef->getArguments();
        $this->assertCount(1, $constructorArgs);
        $this->assertInstanceOf(Reference::class, $constructorArgs[0]);
        $this->assertSame('my_handler', (string) $constructorArgs[0]);

        $this->assertTrue($container->hasAlias(Storage::class));
        $alias = $container->getAlias(Storage::class);
        $this->assertSame('swoole_bundle.session.session_handler_storage', (string) $alias);

        $handlerDef = $container->getDefinition('my_handler');
        $this->assertTrue($handlerDef->hasTag(ContainerConstants::TAG_STATEFUL_SERVICE));
    }

    public function testProcessTagsResolvedHandlerWhenHandlerIdIsAnAlias(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter(ContainerConstants::PARAM_COROUTINES_ENABLED, true);
        $container->prependExtensionConfig('framework', [
            'session' => [
                'handler_id' => 'my_handler_alias',
            ],
        ]);
        $container->setDefinition('my_handler', new Definition(SessionHandlerInterface::class));
        $container->setAlias('my_handler_alias', 'my_handler');

        (new SessionHandlerStorageConfiguratorPass())->process($container);

        $this->assertTrue($container->hasDefinition('swoole_bundle.session.session_handler_storage'));
        $this->assertTrue($container->hasAlias(Storage::class));

        $handlerDef = $container->getDefinition('my_handler');
        $this->assertTrue($handlerDef->hasTag(ContainerConstants::TAG_STATEFUL_SERVICE));
    }
}

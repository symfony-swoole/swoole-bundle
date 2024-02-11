<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass;

use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\ContainerConstants;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\CoWrapper;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Messenger\ContextReleasingTransportHandler;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Messenger\SwooleServerTaskTransportFactory;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Messenger\SwooleServerTaskTransportHandler;
use SwooleBundle\SwooleBundle\Server\HttpServer;
use SwooleBundle\SwooleBundle\Server\TaskHandler\TaskHandler;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Messenger\MessageBusInterface;

final class MessengerTransportFactoryPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->has(MessageBusInterface::class)) {
            return;
        }

        $transportFactory = new Definition(SwooleServerTaskTransportFactory::class);
        $transportFactory->setArgument('$server', new Reference(HttpServer::class));
        $transportFactory->addTag('messenger.transport_factory');
        $container->setDefinition(SwooleServerTaskTransportFactory::class, $transportFactory);

        $transportHandler = new Definition(SwooleServerTaskTransportHandler::class);
        $transportHandler->setArgument('$bus', new Reference(MessageBusInterface::class));
        $transportHandler->setArgument('$decorated', new Reference(SwooleServerTaskTransportHandler::class . '.inner'));
        $transportHandler->setDecoratedService(TaskHandler::class, null, -10);
        $container->setDefinition(SwooleServerTaskTransportHandler::class, $transportHandler);

        if ((bool) $container->getParameter(ContainerConstants::PARAM_COROUTINES_ENABLED) === false) {
            return;
        }

        $svcResettingHandler = new Definition(ContextReleasingTransportHandler::class);
        $svcResettingHandler->setArgument(
            '$decorated',
            new Reference(ContextReleasingTransportHandler::class . '.inner')
        );
        $svcResettingHandler->setArgument('$coWrapper', new Reference(CoWrapper::class));
        // this decorator has to be on top, so it can reset coroutine context after coroutine is finished
        $svcResettingHandler->setDecoratedService(TaskHandler::class, null, -10000);
        $container->setDefinition(ContextReleasingTransportHandler::class, $svcResettingHandler);
    }
}

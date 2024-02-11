<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Monolog;

use Monolog\Handler\StreamHandler as OriginalStreamHandler;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices\CompileProcessor;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices\Proxifier;
use SwooleBundle\SwooleBundle\Component\Locking\Mutex;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class MonologProcessor implements CompileProcessor
{
    public function process(ContainerBuilder $container, Proxifier $proxifier): void
    {
        $loggerAliases = array_filter(
            $container->getAliases(),
            static fn(Alias $alias): bool => str_starts_with((string) $alias, 'monolog.logger')
        );
        $loggerSvcIds = array_map(static fn(Alias $alias): string => (string) $alias, $loggerAliases);
        $loggerSvcIds = array_unique($loggerSvcIds);

        foreach ($loggerSvcIds as $loggerSvcId) {
            $proxifier->proxifyService($loggerSvcId);
        }

        $this->overrideStreamHandlers($container);
    }

    private function overrideStreamHandlers(ContainerBuilder $container): void
    {
        $streamHandlers = array_filter(
            $container->getDefinitions(),
            static fn(Definition $def): bool => $def->getClass() === OriginalStreamHandler::class
        );

        $handlerMutexDef = new Definition(Mutex::class);
        $handlerMutexDef->setFactory([new Reference('swoole_bundle.service_pool.locking'), 'newMutex']);
        $container->setDefinition('swoole_bundle.monolog_stream_handler.locking', $handlerMutexDef);

        foreach ($streamHandlers as $streamHandlerDef) {
            $streamHandlerDef->setClass(StreamHandler::class);
            $streamHandlerDef->addMethodCall(
                'setMutex',
                [new Reference('swoole_bundle.monolog_stream_handler.locking')]
            );
        }
    }
}

<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Monolog;

use K911\Swoole\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices\CompileProcessor;
use K911\Swoole\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices\Proxifier;
use K911\Swoole\Component\Locking\Mutex;
use Monolog\Handler\StreamHandler as OriginalStreamHandler;
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
            fn (Alias $alias): bool => str_starts_with((string) $alias, 'monolog.logger')
        );
        $loggerSvcIds = array_map(fn (Alias $alias): string => (string) $alias, $loggerAliases);
        $loggerSvcIds = array_unique($loggerSvcIds);
        $handlers = [];

        foreach ($loggerSvcIds as $loggerSvcId) {
            $loggerDef = $container->getDefinition($loggerSvcId);
            $handlers = array_merge($handlers, $this->getHandlersFromConstructor($loggerDef));
            $handlers = array_merge($handlers, $this->getPushedHandlers($loggerDef));
            $proxifier->proxifyService($loggerSvcId);
        }

        $this->overrideStreamHandlers($container, $handlers);
    }

    /**
     * @return array<string, Reference>
     */
    private function getHandlersFromConstructor(Definition $loggerDef): array
    {
        $arguments = $loggerDef->getArguments();

        if (!isset($arguments[1])) {
            return [];
        }

        $constrHandlers = $loggerDef->getArgument(1);
        $handlerRefs = [];

        if (is_array($constrHandlers) && count($constrHandlers) > 0) {
            foreach ($constrHandlers as $handler) {
                $handlerRefs[(string) $handler] = $handler;
            }
        }

        return $handlerRefs;
    }

    /**
     * @return array<string, Reference>
     */
    private function getPushedHandlers(Definition $loggerDef): array
    {
        $calls = $loggerDef->getMethodCalls();
        $handlers = [];

        foreach ($calls as $call) {
            if ('pushHandler' === $call[0]) {
                $handlers[(string) $call[1][0]] = $call[1][0];
            }
        }

        return $handlers;
    }

    private function overrideStreamHandlers(ContainerBuilder $container, array $handlers): void
    {
        $streamHandlers = array_filter(
            $container->getDefinitions(),
            fn (Definition $def): bool => OriginalStreamHandler::class === $def->getClass()
        );

        $handlerMutexDef = new Definition(Mutex::class);
        $handlerMutexDef->setFactory([new Reference('swoole_bundle.service_pool.locking'), 'newMutex']);
        $container->setDefinition('swoole_bundle.monolog_stream_handler.locking', $handlerMutexDef);

        foreach ($streamHandlers as $streamHandlerId => $streamHandlerDef) {
            $streamHandlerDef->setClass(StreamHandler::class);
            $streamHandlerDef->addMethodCall(
                'setMutex',
                [new Reference('swoole_bundle.monolog_stream_handler.locking')]
            );
        }
    }
}

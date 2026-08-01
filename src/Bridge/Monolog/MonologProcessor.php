<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Monolog;

use Monolog\Handler\RotatingFileHandler as OriginalRotatingFileHandler;
use Monolog\Handler\StreamHandler as OriginalStreamHandler;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices\CompileProcessor;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices\Proxifier;
use SwooleBundle\SwooleBundle\Common\Adapter\Swoole;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

final class MonologProcessor implements CompileProcessor
{
    /**
     * Handlers writing to a shared stream, mapped to their coroutine safe replacement. Matched by exact
     * class name, a handler extending one of them keeps its own write() and would not be made safe by the
     * replacement anyway.
     */
    private const array COROUTINE_SAFE_HANDLERS = [
        OriginalStreamHandler::class => StreamHandler::class,
        OriginalRotatingFileHandler::class => RotatingFileHandler::class,
    ];

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
        foreach ($container->getDefinitions() as $handlerDef) {
            $handlerClass = $handlerDef->getClass();

            if ($handlerClass === null || !isset(self::COROUTINE_SAFE_HANDLERS[$handlerClass])) {
                continue;
            }

            $handlerDef->setClass(self::COROUTINE_SAFE_HANDLERS[$handlerClass]);
            $handlerDef->addMethodCall('setSwoole', [new Reference(Swoole::class)]);
        }
    }
}

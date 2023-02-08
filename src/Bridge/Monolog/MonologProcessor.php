<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Monolog;

use K911\Swoole\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices\CompileProcessor;
use K911\Swoole\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices\Proxifier;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\ContainerBuilder;

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

        foreach ($loggerSvcIds as $loggerSvcId) {
            $proxifier->proxifyService($loggerSvcId);
        }
    }
}

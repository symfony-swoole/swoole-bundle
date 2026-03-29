<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Router;

use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices\CompileProcessor;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices\Proxifier;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class RouterProcessor implements CompileProcessor
{
    public function process(ContainerBuilder $container, Proxifier $proxifier): void
    {
        if (
            !$container->hasDefinition('debug.event_dispatcher')
            || !$container->hasDefinition('debug.event_dispatcher.inner')
        ) {
            return;
        }

        // the debug event dispatcher needs to be coupled to the original event dispatcher, because
        // it registers listeners to the original dispatcher
        $dispatcherDef = $container->findDefinition('debug.event_dispatcher.inner');
        $dispatcherDef->setShared(false);
    }
}

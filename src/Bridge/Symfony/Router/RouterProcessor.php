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
            $proxifier->proxifyService('event_dispatcher');

            return;
        }

        // the debug event dispatcher needs to be proxified
        // it registers listeners to the original dispatcher
        // for yet unknown reason the debug.event_dispatcher has to be proxified in StatefulServicesPass
        $proxifier->proxifyService('debug.event_dispatcher.inner');
    }
}

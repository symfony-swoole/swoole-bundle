<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\EventDispatcher;

use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices\CompileProcessor;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices\Proxifier;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\ContainerConstants;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class EventDispatcherProcessor implements CompileProcessor
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

        // the debug event dispatcher needs to be coupled to the original event dispatcher, because
        // it registers listene≠rs to the original dispatcher
        $container->findDefinition('debug.event_dispatcher.inner')
            ->setShared(false);

        $debugDispatcherDef = $container->getDefinition('debug.event_dispatcher');
        $debugDispatcherDef->addTag(ContainerConstants::TAG_STATEFUL_SERVICE);
    }
}

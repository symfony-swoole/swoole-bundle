<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass;

use SwooleBundle\SwooleBundle\Bridge\Symfony\HttpFoundation\StreamedResponseListener;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * Replaces Symfony's native StreamedResponseListener with a custom one compatible with Swoole.
 */
final class StreamedResponseListenerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $definitionId = 'streamed_response_listener';

        $newDefinition = new Definition(StreamedResponseListener::class);
        $newDefinition->setAutoconfigured(true);
        $newDefinition->addTag('kernel.event_subscriber');

        $container->setDefinition($definitionId, $newDefinition);
    }
}

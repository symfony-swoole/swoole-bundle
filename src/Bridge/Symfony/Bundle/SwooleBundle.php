<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle;

use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\{
    BlackfireMonitoringPass,
    DebugLogProcessorPass,
    ExceptionHandlerPass,
    FinalizeDefinitionsAfterRemovalPass,
    MessengerTransportFactoryPass,
    SessionStorageListenerPass,
    StatefulServicesPass,
    StreamedResponseListenerPass,
};
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class SwooleBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new BlackfireMonitoringPass());
        $container->addCompilerPass(new DebugLogProcessorPass());
        $container->addCompilerPass(new StreamedResponseListenerPass());
        $container->addCompilerPass(new SessionStorageListenerPass());
        $container->addCompilerPass(new MessengerTransportFactoryPass());
        $container->addCompilerPass(new ExceptionHandlerPass());
        $container->addCompilerPass(new StatefulServicesPass(), PassConfig::TYPE_BEFORE_REMOVING, -10000);
        $container->addCompilerPass(new FinalizeDefinitionsAfterRemovalPass(), PassConfig::TYPE_AFTER_REMOVING, -10000);
    }
}

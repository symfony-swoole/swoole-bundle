<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle;

use Assert\Assertion;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\{BlackfireMonitoringPass,
    ExceptionHandlerPass,
    FinalizeDefinitionsAfterRemovalPass,
    MessengerTransportFactoryPass,
    RouterOptimizerPass,
    SessionStorageListenerPass,
    StatefulServicesPass,
    StreamedResponseListenerPass};
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\ContainerConstants;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\ErrorHandler\ErrorHandler;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\HttpKernel\Debug\ErrorHandlerConfigurator;

final class SwooleBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new BlackfireMonitoringPass());
        $container->addCompilerPass(new StreamedResponseListenerPass());
        $container->addCompilerPass(new SessionStorageListenerPass());
        $container->addCompilerPass(new MessengerTransportFactoryPass());
        $container->addCompilerPass(new ExceptionHandlerPass());
        $container->addCompilerPass(new RouterOptimizerPass());
        $container->addCompilerPass(new StatefulServicesPass(), PassConfig::TYPE_BEFORE_REMOVING, -10000);
        $container->addCompilerPass(new FinalizeDefinitionsAfterRemovalPass(), PassConfig::TYPE_AFTER_REMOVING, -10000);
    }

    /**
     * in coroutine mode, we need to override the default error handler to not get an exception
     * during normal runtime
     */
    public function boot(): void
    {
        Assertion::notNull($this->container);

        if (!$this->container->hasParameter(ContainerConstants::PARAM_COROUTINES_ENABLED)) {
            return;
        }

        if (!$this->container->getParameter(ContainerConstants::PARAM_COROUTINES_ENABLED)) {
            return;
        }

        /** @var ErrorHandler $handler */
        $handler = $this->container->get('swoole_bundle.error_handler.symfony_error_handler');
        $debug = $this->container->getParameter('kernel.debug');

        if ($debug) {
            // override the default error handler registered earlier by Symfony to not get an exception
            set_exception_handler([$handler, 'handleException']);
        }

        $handler = ErrorHandler::register($handler, true);
        $configurator = $this->container->get('debug.error_handler_configurator');

        Assertion::isInstanceOf($configurator, ErrorHandlerConfigurator::class);

        $configurator->configure($handler);
    }
}

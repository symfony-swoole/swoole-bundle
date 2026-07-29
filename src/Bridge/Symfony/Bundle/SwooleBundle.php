<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle;

use Assert\Assertion;
use RuntimeException;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\{BlackfireMonitoringPass,
    CacheWarmupFixerPass,
    ExceptionHandlerPass,
    FinalizeDefinitionsAfterRemovalPass,
    MessengerTransportFactoryPass,
    RouterOptimizerPass,
    SessionHandlerStorageConfiguratorPass,
    StatefulServicesPass,
    StreamedResponseListenerPass,
    SwooleTableStorageConfiguratorPass};
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
        $container->addCompilerPass(new MessengerTransportFactoryPass());
        $container->addCompilerPass(new ExceptionHandlerPass());
        $container->addCompilerPass(new RouterOptimizerPass());
        $container->addCompilerPass(new StatefulServicesPass(), PassConfig::TYPE_BEFORE_REMOVING, -10000);
        $container->addCompilerPass(new FinalizeDefinitionsAfterRemovalPass(), PassConfig::TYPE_AFTER_REMOVING, -10000);
        $container->addCompilerPass(new SwooleTableStorageConfiguratorPass());
        $container->addCompilerPass(new SessionHandlerStorageConfiguratorPass());
        $container->addCompilerPass(new CacheWarmupFixerPass());
    }

    /**
     * in coroutine mode, we need to override the default error handler to not get an exception
     * during normal runtime
     */
    public function boot(): void
    {
        // phpcs:ignore SlevomatCodingStandard.Variables.DisallowSuperGlobalVariable
        if (!isset($_SERVER['APP_RUNTIME_MODE']) && !isset($_ENV['APP_RUNTIME_MODE'])) {
            throw new RuntimeException(
                'APP_RUNTIME_MODE needs to be set either in $_SERVER or $_ENV. '
                . 'For usual cases the configured value should be \'web=1&worker=1\' for this bundle to work properly.',
            );
        }

        Assertion::notNull($this->container);

        if (!$this->container->hasParameter(ContainerConstants::PARAM_COROUTINES_ENABLED)) {
            return;
        }

        if (!$this->container->getParameter(ContainerConstants::PARAM_COROUTINES_ENABLED)) {
            return;
        }

        /** @var ErrorHandler $handler */
        $handler = $this->container->get('swoole_bundle.error_handler.symfony_error_handler');

        // override the default error handler registered earlier by Symfony to not get an exception
        set_exception_handler([$handler, 'handleException']);

        $handler = ErrorHandler::register($handler, true);
        $configurator = $this->container->get('debug.error_handler_configurator');

        Assertion::isInstanceOf($configurator, ErrorHandlerConfigurator::class);

        $configurator->configure($handler);
    }
}

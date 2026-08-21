<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle;

use Assert\Assertion;
use RuntimeException;
use SwooleBundle\SwooleBundle\Bridge\CommonSwoole\SystemSwooleFactory;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\{BlackfireMonitoringPass,
    CacheWarmupFixerPass,
    ControllerResolverPass,
    DebugContainerRedumpPass,
    ExceptionHandlerPass,
    FinalizeDefinitionsAfterRemovalPass,
    HealthCheckPass,
    MessengerTransportFactoryPass,
    RouterOptimizerPass,
    SessionHandlerStorageConfiguratorPass,
    StatefulServicesPass,
    StreamedResponseListenerPass,
    SwooleTableStorageConfiguratorPass};
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\ContainerConstants;
use SwooleBundle\SwooleBundle\Bridge\Symfony\ErrorHandler\ContextualErrorHandler;
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
        $container->addCompilerPass(new HealthCheckPass());
        $container->addCompilerPass(new ControllerResolverPass());
        $container->addCompilerPass(new StatefulServicesPass(), PassConfig::TYPE_BEFORE_REMOVING, -10000);
        // below StatefulServicesPass, so that what it rewrites is the container with the pools in it
        $container->addCompilerPass(new DebugContainerRedumpPass(), PassConfig::TYPE_BEFORE_REMOVING, -20000);
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

        // from now on, error/exception handler overrides are isolated per coroutine
        ContextualErrorHandler::register(SystemSwooleFactory::newFactoryInstance()->newInstance());

        /** @var ErrorHandler $handler */
        $handler = $this->container->get('swoole_bundle.error_handler.symfony_error_handler');

        // override the default error handler registered earlier by Symfony to not get an exception
        set_exception_handler([$handler, 'handleException']);

        // ErrorHandler::register() claims the root handler slot - a write to its private $isRoot -
        // when get_error_handler() comes back null. $handler is a pooled proxy, and ProxyManager
        // resolves the scope of a private property write from the calling frame's object; a static
        // method like register() has none, so it falls back to a scope that cannot see
        // ErrorHandler's privates and the write fatals with "Cannot access private property".
        //
        // Null is a perfectly ordinary answer here. ContextualErrorHandler::register() above holds
        // the real handler slot itself and reports per-coroutine handlers instead, so an empty
        // context stack reads as "no handler" even though PHP has one. The stack is empty whenever
        // nothing registered a handler before this point: FrameworkBundle::boot() only seeds one
        // when symfony/runtime is absent, and a kernel booted outside a runtime entrypoint - as
        // PHPUnit extensions do to get at the container - has nothing else that would.
        //
        // Registering the handler ourselves first sends register() down its non-root branch and
        // puts it on the global context stack, which is where coroutines inherit it from anyway.
        // Nothing is lost: isRoot only decides whether reRegister() repeats the error-type mask,
        // and handleError() filters on thrownErrors/loggedErrors regardless.
        if (get_error_handler() === null) {
            set_error_handler([$handler, 'handleError']);
        }

        $handler = ErrorHandler::register($handler, true);
        $configurator = $this->container->get('debug.error_handler_configurator');

        Assertion::isInstanceOf($configurator, ErrorHandlerConfigurator::class);

        $configurator->configure($handler);
    }
}

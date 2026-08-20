<?php

declare(strict_types=1);

use Monolog\Formatter\LineFormatter;
use Ramsey\Uuid\UuidFactory;
use Ramsey\Uuid\UuidFactoryInterface;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\SimpleResetter;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Controller\DoctrineController;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Controller\SleepController;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\EventHandler\LifecycleEventsEventHandler;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Service\AdvancedDoctrineUsage;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Service\DecorationTestDummyService;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Service\DefaultDummyService;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Service\DummyService;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Service\InMemoryRepository;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Service\RepositoryFactory;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Service\UnusedServiceToRemove;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->defaults()
        ->autowire()
        ->autoconfigure();

    $services->load('SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\\', __DIR__ . '/../../TestBundle/*')
        ->exclude([
            __DIR__ . '/../../TestBundle/{Message,Test,Controller,Migrations,Resetter,Service/NoAutowiring}',
            // depends on fixture services (security.event_dispatcher.main/api) only registered in the
            // coroutines_security* environments, so it must not be auto-registered everywhere else.
            __DIR__ . '/../../TestBundle/Command/SecurityFirewallEventDispatcherProxyCheckCommand.php',
            // same story for security.access.decision_manager, which SecurityBundle only brings along in
            // the coroutines_security* environments.
            __DIR__ . '/../../TestBundle/Command/AccessDecisionManagerProxyCheckCommand.php',
            // and for security.authorization_checker, the class one layer up that calls into it.
            __DIR__ . '/../../TestBundle/Command/AuthorizationCheckerProxyCheckCommand.php',
            // same story for security.firewall.map.
            __DIR__ . '/../../TestBundle/Command/SecurityFirewallContextProxyCheckCommand.php',
            // depends on twig.profile, which only exists where the Twig profiler is on.
            __DIR__ . '/../../TestBundle/Command/TwigProfileProxyCheckCommand.php',
            // depends on doctrine.debug_data_holder, which only exists where profiling is on.
            __DIR__ . '/../../TestBundle/Command/DoctrineQueryLogResetCheckCommand.php',
            // same story for data_collector.messenger.
            __DIR__ . '/../../TestBundle/Command/MessengerTraceableBusProxyCheckCommand.php',
            // asks for one named messenger transport, which only the task_worker_messenger environment
            // configures - and which is a private service autowiring could not resolve anywhere.
            __DIR__ . '/../../TestBundle/Command/MessengerTransportReportCommand.php',
            __DIR__ . '/../../TestBundle/HealthCheck',
            // decorates a specific handler and is registered explicitly by the coroutines environment.
            // Auto-registering it would make autoconfiguration tag it as a bootable service and autowire
            // $decorated to the outermost RequestHandler, in every environment.
            __DIR__ . '/../../TestBundle/RequestHandler',
        ]);

    $services->load(
        'SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Controller\\',
        __DIR__ . '/../../TestBundle/Controller'
    )
        ->tag('controller.service_arguments')
        ->exclude([
            // Rewritten mid-test to prove a reload took effect, so it must stay out of the compiled
            // container - a registered service is loaded before the workers fork, which is exactly what
            // makes a file non-reloadable. The glob covers the per-worker copies parallel runs render.
            __DIR__ . '/../../TestBundle/Controller/ReplacedContentTestController*.php',
            // constructor needs 4 explicit LeakyResource/LeakyDataCollector args that only exist in the
            // coroutines_profiler environment, so it must not be auto-registered everywhere else.
            __DIR__ . '/../../TestBundle/Controller/LeakyServicesController.php',
            // asks for the traced http client, which only exists where the profiler and the http client
            // are both on, and for a mock web server url only the feature test publishes - so the
            // coroutines_profiler environment registers it and nowhere else may.
            __DIR__ . '/../../TestBundle/Controller/TracedHttpClientController.php',
        ]);

    $services->set(DoctrineController::class)
        ->arg('$registry', service('doctrine'))
        ->arg('$resetters', [
        ])
        ->arg('$dataHolder', service('doctrine.debug_data_holder')->ignoreOnInvalid())
        ->tag('controller.service_arguments');

    $services->set(SleepController::class)
        ->arg('$connection', service('doctrine.dbal.default_connection'))
        ->arg('$container', service('service_container'))
        ->tag('controller.service_arguments');

    $services->alias(UuidFactoryInterface::class, UuidFactory::class);

    $services->set(UuidFactory::class);

    $services->alias(DummyService::class, DefaultDummyService::class);

    $services->set(DefaultDummyService::class)
        ->arg('$entityManager', service('doctrine.orm.default_entity_manager'))
        ->arg('$uuidFactory', service(UuidFactoryInterface::class))
        ->arg('$factory', service(RepositoryFactory::class))
        ->tag('swoole_bundle.decorated_stateful_service');

    $services->set(AdvancedDoctrineUsage::class)
        ->arg('$uuidFactory', service(UuidFactoryInterface::class))
        ->arg('$doctrine', service('doctrine'));

    $services->set(DecorationTestDummyService::class)
        ->decorate(DefaultDummyService::class)
        ->arg('$decorated', service('.inner'));

    $services->set(RepositoryFactory::class)
        ->tag('swoole_bundle.unmanaged_factory', [
            'factoryMethod' => 'newInstance',
            'returnType' => InMemoryRepository::class,
            'limit' => 15,
            'resetter' => 'inmemory_repository_resetter',
        ]);

    $services->set('inmemory_repository_resetter', SimpleResetter::class)
        ->arg('$resetFn', 'reset');

    $services->set(UnusedServiceToRemove::class)
        ->tag('kernel.reset', [
            'method' => 'reset',
        ]);

    $services->set(LifecycleEventsEventHandler::class)
        ->tag('swoole_bundle.stateful_service');

    $services->set('monolog.formatter.full_trace', LineFormatter::class)
        ->arg('$format', null)
        ->arg('$dateFormat', null)
        ->arg('$allowInlineLineBreaks', true)
        ->arg('$ignoreEmptyContextAndExtra', false)
        ->arg('$includeStacktraces', true);
};

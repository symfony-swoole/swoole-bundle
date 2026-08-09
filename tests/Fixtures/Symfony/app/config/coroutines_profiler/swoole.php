<?php

declare(strict_types=1);

use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Command\TwigProfileProxyCheckCommand;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Controller\CoroutinesTaskController;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Controller\LeakyServicesController;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\DataCollector\LeakyDataCollector;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\DependencyInjection\CompilerPass\{
    CounterCompileProcessor,
    SleepingCounterCompileProcessor,
};
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\MessageHandler\RunDummyHandler;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\MessageHandler\SleepAndAppendHandler;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Service\AlwaysReset;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Service\AlwaysResetSafe;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Service\LeakyResource;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Service\NonSharedExample;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Service\ShouldBeProxified;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Service\ShouldBeProxified2;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $parameters = $containerConfigurator->parameters();

    $parameters->set('env(WORKER_COUNT)', 1);

    $parameters->set('env(TASK_WORKER_COUNT)', 1);

    $parameters->set('env(REACTOR_COUNT)', 1);

    $containerConfigurator->extension('swoole', [
        'http_server' => [
            'static' => [
                'strategy' => 'advanced',
                'public_dir' => '%kernel.project_dir%/public',
            ],
            'exception_handler' => [
                'type' => 'symfony',
            ],
        ],
        'task_worker' => [
            'settings' => [
                'worker_count' => '%env(int:TASK_WORKER_COUNT)%',
            ],
            'services' => [
                'reset_handler' => true,
            ],
        ],
        'platform' => [
            'coroutines' => [
                'enabled' => true,
                'max_concurrency' => 30,
                'max_service_instances' => 20,
                'stateful_services' => [
                    ShouldBeProxified::class,
                ],
                'compile_processors' => [
                    [
                        'class' => SleepingCounterCompileProcessor::class,
                        'priority' => 10,
                    ],
                    CounterCompileProcessor::class,
                ],
                'doctrine_processor_config' => [
                    'limits' => [
                        'default' => 12,
                    ],
                ],
            ],
        ],
    ]);

    $services = $containerConfigurator->services();

    $services->defaults()
        ->autowire()
        ->autoconfigure();

    $services->set(ShouldBeProxified2::class)
        ->tag('swoole_bundle.stateful_service', [
            'limit' => 10,
        ]);

    $services->set(CoroutinesTaskController::class)
        ->tag('controller.service_arguments');

    $services->set(SleepAndAppendHandler::class)
        ->tag('messenger.message_handler');

    $services->set(RunDummyHandler::class)
        ->tag('messenger.message_handler');

    $services->set(AlwaysReset::class)
        ->tag('swoole_bundle.stateful_service', [
            'reset_on_each_request' => true,
        ]);

    $services->set(AlwaysResetSafe::class)
        ->tag('swoole_bundle.safe_stateful_service', [
            'reset_on_each_request' => true,
        ]);

    $services->set(NonSharedExample::class)
        ->public()
        ->share(false)
        ->tag('swoole_bundle.stateful_service')
        ->tag('kernel.reset', ['method' => '?optionalReset']);

    // Reproducer fixtures for the "pooled ResetInterface services are never reset" bug.
    //
    // A pooled service only gets a resetter attached if it appears in Symfony's `services_resetter`,
    // which lists exactly the services tagged `kernel.reset`. Autoconfiguration adds that tag to every
    // ResetInterface implementation - but bundles registering their own services (FrameworkBundle's
    // data collectors, MonologBundle's loggers, DoctrineBundle's and SecurityBundle's debug decorators)
    // do not autoconfigure them, so they carry no such tag. `autoconfigure(false)` below reproduces
    // exactly that: without the ResetInterface fallback in StatefulServicesPass these two services are
    // pooled with a NULL resetter, keep being recycled across coroutines, and accumulate forever.
    $services->set('leaky_resource.stateful_only', LeakyResource::class)
        ->public()
        ->autoconfigure(false)
        ->tag('swoole_bundle.stateful_service');

    $services->set('leaky_data_collector.plain', LeakyDataCollector::class)
        ->public()
        ->autoconfigure(false)
        ->tag('data_collector', ['id' => 'leaky_plain']);

    // The remaining three are the already-working registrations, kept as regression guards so a future
    // change to the resetter resolution cannot silently break them either.
    $services->set('leaky_resource.kernel_reset', LeakyResource::class)
        ->public()
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set('leaky_resource.reset_on_each_request', LeakyResource::class)
        ->public()
        ->tag('kernel.reset', ['method' => 'reset'])
        ->tag('swoole_bundle.stateful_service', ['reset_on_each_request' => true]);

    $services->set('leaky_data_collector.reset_on_each_request', LeakyDataCollector::class)
        ->public()
        ->tag('data_collector', ['id' => 'leaky_reset_on_each_request'])
        ->tag('swoole_bundle.stateful_service', ['reset_on_each_request' => true]);

    // depends on twig.profile, which the Twig profiler only registers where it is turned on - here.
    $services->set(TwigProfileProxyCheckCommand::class);

    $services->set(LeakyServicesController::class)
        ->arg('$statefulOnlyResource', service('leaky_resource.stateful_only'))
        ->arg('$kernelResetResource', service('leaky_resource.kernel_reset'))
        ->arg('$resetOnEachRequestResource', service('leaky_resource.reset_on_each_request'))
        ->arg('$dataCollector', service('leaky_data_collector.plain'))
        ->arg('$resetOnEachRequestDataCollector', service('leaky_data_collector.reset_on_each_request'))
        ->tag('controller.service_arguments');
};

<?php

declare(strict_types=1);

use Blackfire\Client;
use SwooleBundle\SwooleBundle\Bridge\Upscale\Blackfire\Profiling\ProfilerActivator;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Blackfire\CollectionProfiler;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Controller\CoroutinesTaskController;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\DependencyInjection\CompilerPass\{
    ResetCountCompileProcessor,
    SleepingCounterCompileProcessor,
};
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\MessageHandler\RunDummyHandler;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\MessageHandler\SleepAndAppendHandler;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Service\AlwaysReset;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Service\AlwaysResetSafe;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Service\InMemoryRepository;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Service\NonSharedExample;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Service\RepositoryFactory;
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
            'exception_handler' => [
                'type' => 'symfony',
            ],
            'services' => [
                'blackfire_profiler' => true,
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
                'stateful_services' => [
                    ShouldBeProxified::class,
                ],
                'compile_processors' => [
                    [
                        'class' => SleepingCounterCompileProcessor::class,
                        'priority' => 10,
                    ],
                    ResetCountCompileProcessor::class,
                ],
            ],
        ],
    ]);

    $services = $containerConfigurator->services();

    $services->defaults()
        ->autowire()
        ->autoconfigure();

    $services->set(ShouldBeProxified2::class)
        ->tag('swoole_bundle.stateful_service');

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
        ->tag('swoole_bundle.stateful_service');

    $services->set(RepositoryFactory::class)
        ->tag('swoole_bundle.unmanaged_factory', [
            'factoryMethod' => 'newInstance',
            'returnType' => InMemoryRepository::class,
            'limit' => 1000,
            'resetter' => 'inmemory_repository_resetter',
        ]);

    $services->set(Client::class);

    $services->set(CollectionProfiler::class)
        ->arg('$client', service(Client::class));

    $services->set(ProfilerActivator::class)
        ->arg('$profiler', service(CollectionProfiler::class));
};

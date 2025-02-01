<?php

declare(strict_types=1);

use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Controller\CoroutinesTaskController;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\DependencyInjection\CompilerPass\{
    ResetCountCompileProcessor,
    SleepingCounterCompileProcessor,
};
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\MessageHandler\RunDummyHandler;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\MessageHandler\SleepAndAppendHandler;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Service\AlwaysReset;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Service\AlwaysResetSafe;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Service\NonSharedExample;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Service\ShouldBeProxified;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Service\ShouldBeProxified2;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

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
                    ResetCountCompileProcessor::class,
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
};

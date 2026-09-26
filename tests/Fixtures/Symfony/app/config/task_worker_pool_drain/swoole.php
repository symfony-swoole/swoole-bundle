<?php

/**
 * A task worker whose command leaves a pooled connection behind and ends, so the worker is recycled with it -
 * for TaskWorkerDrainsServicePoolsTest.
 */

declare(strict_types=1);

use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Command\TaskWorkerOpenConnectionCommand;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Service\GoodbyeSayingConnection;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension('swoole', [
        'http_server' => [
            'exception_handler' => [
                'type' => 'symfony',
            ],
            'settings' => [
                'worker_count' => 1,
            ],
        ],
        'task_worker' => [
            'settings' => [
                'worker_count' => 1,
            ],
            'commands' => [
                'test:task-worker:open-connection',
            ],
        ],
        'platform' => [
            'coroutines' => [
                'enabled' => true,
                'max_concurrency' => 30,
                'stateful_services' => [
                    GoodbyeSayingConnection::class,
                ],
            ],
        ],
    ]);

    $services = $containerConfigurator->services();

    $services->defaults()
        ->autowire()
        ->autoconfigure();

    $services->set(GoodbyeSayingConnection::class);
    $services->set(TaskWorkerOpenConnectionCommand::class);
};

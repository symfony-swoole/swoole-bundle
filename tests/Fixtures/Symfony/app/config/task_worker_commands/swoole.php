<?php

/**
 * EXPERIMENTAL feature - long running console commands in task workers, coroutines on.
 *
 * Two groups over two task workers, the second holding two commands to prove several can share one
 * worker. Group 0 is a single command, so the two shapes are covered at once.
 */

declare(strict_types=1);

use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Command\TaskWorkerHeartbeatCommand;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension('swoole', [
        'http_server' => [
            // As every other coroutines fixture does - the pooled error handler is what the coroutine
            // path expects, and without it the container has no symfony_error_handler to hand it.
            'exception_handler' => [
                'type' => 'symfony',
            ],
            'settings' => [
                'worker_count' => 1,
            ],
        ],
        'task_worker' => [
            'settings' => [
                'worker_count' => 2,
            ],
            'commands' => [
                'test:task-worker:heartbeat solo',
                [
                    'test:task-worker:heartbeat shared-a',
                    'test:task-worker:heartbeat shared-b',
                ],
            ],
        ],
        'platform' => [
            'coroutines' => [
                'enabled' => true,
                'max_concurrency' => 30,
            ],
        ],
    ]);

    $services = $containerConfigurator->services();

    $services->defaults()
        ->autowire()
        ->autoconfigure();

    $services->set(TaskWorkerHeartbeatCommand::class);
};

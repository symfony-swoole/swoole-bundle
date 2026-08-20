<?php

/**
 * EXPERIMENTAL feature - a task worker command that ends on its own, so its worker is recycled.
 *
 * A slot of its own rather than task_worker_commands', because the heartbeat files are named after the
 * slot and nothing else - two environments sharing one would collide the moment the suites run in
 * parallel.
 */

declare(strict_types=1);

use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Command\TaskWorkerHeartbeatCommand;
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
                'test:task-worker:heartbeat recycle --interval=100 --max-ticks=20',
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

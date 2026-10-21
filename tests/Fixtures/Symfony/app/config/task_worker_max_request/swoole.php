<?php

/**
 * EXPERIMENTAL feature - a task worker command alongside worker_max_request recycling.
 *
 * The http worker is recycled after three requests. The command in the task worker must not notice:
 * an http worker being replaced is not the server going down.
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
                // Swoole jitters the real threshold up from this, so a test driving it sends
                // comfortably more requests than the number itself.
                'worker_max_request' => 3,
            ],
        ],
        'task_worker' => [
            'settings' => [
                'worker_count' => 1,
            ],
            'commands' => [
                'test:task-worker:heartbeat maxreq',
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

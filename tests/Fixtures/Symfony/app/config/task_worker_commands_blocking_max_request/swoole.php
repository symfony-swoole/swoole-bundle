<?php

/**
 * EXPERIMENTAL feature - worker_max_request with blocking task worker commands, from an env var.
 *
 * The combination is refused, because every recycle of the http worker would stop the commands for good.
 * Taken from an env var, the number is a placeholder while the container compiles, so the compile-time
 * check cannot see it and only the server, starting, can refuse it.
 */

declare(strict_types=1);

use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Command\TaskWorkerHeartbeatCommand;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->parameters()->set('env(WORKER_MAX_REQUEST)', 50);

    $containerConfigurator->extension('swoole', [
        'http_server' => [
            'settings' => [
                'worker_count' => 1,
                'worker_max_request' => '%env(int:WORKER_MAX_REQUEST)%',
            ],
        ],
        'task_worker' => [
            'settings' => [
                'worker_count' => 1,
            ],
            'commands' => [
                'test:task-worker:heartbeat blocking-max-request --stop-check',
            ],
        ],
    ]);

    $services = $containerConfigurator->services();

    $services->defaults()
        ->autowire()
        ->autoconfigure();

    $services->set(TaskWorkerHeartbeatCommand::class);
};

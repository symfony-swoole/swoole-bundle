<?php

/**
 * EXPERIMENTAL feature - the "worker unit" shape.
 *
 * One deployable that answers liveness probes on a port of its own while several worker loops run in
 * a task worker. This is the combination a supervisor-per-consumer deployment cannot express, so it
 * is worth a test of its own rather than being inferred from the two features working separately.
 */

declare(strict_types=1);

use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Command\TaskWorkerHeartbeatCommand;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    // Parallel feature tests move the health process onto a port of their own; ServerTestCase
    // exports the one the current worker owns.
    $containerConfigurator->parameters()->set('env(HEALTH_PORT)', 9997);

    $containerConfigurator->extension('swoole', [
        'http_server' => [
            'exception_handler' => [
                'type' => 'symfony',
            ],
            'healthcheck' => [
                'enabled' => true,
                'port' => '%env(int:HEALTH_PORT)%',
                'checks' => [
                    'interval' => 1,
                    'staleness_threshold' => 3,
                ],
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
                [
                    'test:task-worker:heartbeat unit-a',
                    'test:task-worker:heartbeat unit-b',
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

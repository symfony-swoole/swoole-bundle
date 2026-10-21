<?php

/**
 * EXPERIMENTAL feature - long running console commands in task workers, coroutines off.
 *
 * One command per task worker is the only shape allowed here: without a scheduler the command blocks
 * onWorkerStart and owns the process, so a second command in the same group could never start.
 */

declare(strict_types=1);

use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Command\TaskWorkerHeartbeatCommand;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension('swoole', [
        'http_server' => [
            'settings' => [
                'worker_count' => 1,
            ],
        ],
        'task_worker' => [
            'settings' => [
                'worker_count' => 1,
            ],
            'commands' => [
                // --stop-check, because with coroutines off nothing can hand the command a signal: asking
                // StopCheck from its own loop is the only stop it can get.
                'test:task-worker:heartbeat blocking --stop-check',
            ],
        ],
    ]);

    $services = $containerConfigurator->services();

    $services->defaults()
        ->autowire()
        ->autoconfigure();

    $services->set(TaskWorkerHeartbeatCommand::class);
};

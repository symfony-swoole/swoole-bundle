<?php

/**
 * EXPERIMENTAL feature - a task worker whose command cannot run.
 *
 * The same as task_worker_commands_broken, with coroutines off - which is the shape the command blocks
 * onWorkerStart in, and the one where leaving the worker up is worst: it reaches none of its own
 * callbacks and the manager force-terminates it at max_wait_time. The server has to stop here too.
 */

declare(strict_types=1);

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
                'test:task-worker:there-is-no-such-command',
            ],
        ],
        'platform' => [
            'coroutines' => [
                'enabled' => false,
            ],
        ],
    ]);

    $services = $containerConfigurator->services();

    $services->defaults()
        ->autowire()
        ->autoconfigure();
};

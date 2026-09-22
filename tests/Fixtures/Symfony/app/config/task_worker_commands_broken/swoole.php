<?php

/**
 * EXPERIMENTAL feature - a task worker whose command cannot run.
 *
 * The command line names nothing the console knows, which is the cheapest way to reach the case this
 * environment exists for: the group ends the instant it starts, and a worker recycled into that would
 * be forked into it again for as long as the server ran. The server stops instead, non-zero, so that
 * whatever supervises the container decides what happens next.
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
                'enabled' => true,
                'max_concurrency' => 30,
            ],
        ],
    ]);

    $services = $containerConfigurator->services();

    $services->defaults()
        ->autowire()
        ->autoconfigure();
};

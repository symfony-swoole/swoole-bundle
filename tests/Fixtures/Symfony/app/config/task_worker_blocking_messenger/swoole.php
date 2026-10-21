<?php

/**
 * EXPERIMENTAL feature - does the shared stop flag reach a blocking-mode command at all?
 *
 * Coroutines off, so there is no watchdog coroutine to hand the command a signal. The only thing left
 * that reads the flag is StopMessengerWorkerOnShutdown, on messenger's own WorkerRunningEvent - so a
 * messenger consumer is the one command whose blocking-mode shutdown can be graceful.
 *
 * max_wait_time is distinctive: a force-terminated worker drags shutdown out to it, a cooperative one
 * does not, which is how the two are told apart from outside.
 */

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension('framework', [
        'messenger' => [
            'enabled' => true,
            'transports' => [
                'blocking_probe' => 'in-memory://',
            ],
        ],
    ]);

    $containerConfigurator->extension('swoole', [
        'http_server' => [
            'settings' => [
                'worker_count' => 1,
                'worker_max_wait_time' => 8,
            ],
        ],
        'task_worker' => [
            'settings' => [
                'worker_count' => 1,
            ],
            'commands' => [
                'messenger:consume blocking_probe --sleep=1 -v',
            ],
        ],
    ]);
};

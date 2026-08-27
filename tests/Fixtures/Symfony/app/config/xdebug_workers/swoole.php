<?php

/**
 * Every worker attaches as it starts, and nothing else attaches at all.
 *
 * requests is off on purpose: with it on, a request arriving at an already-attached worker would be
 * indistinguishable from the worker start this environment exists to observe.
 */

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension('swoole', [
        // Counts stated rather than inherited, so the test can assert an exact number of attaches:
        // onWorkerStart runs once in each http worker and once in each task worker, and in neither
        // the master nor the manager. Two and one is also the smallest arrangement that shows both
        // kinds being covered - a task worker is the half no request can ever reach.
        'http_server' => [
            'settings' => [
                'worker_count' => 2,
                'reactor_count' => 1,
            ],
        ],
        'task_worker' => [
            'settings' => [
                'worker_count' => 1,
            ],
        ],
        'platform' => [
            'xdebug' => [
                'requests' => 'off',
                'workers' => true,
                'tasks' => false,
            ],
        ],
    ]);
};

<?php

/**
 * A task worker attaches when a task reaches it, and nothing else attaches.
 *
 * Needs a task transport to have tasks at all, so messenger routes RunDummy over swoole://task - the
 * same arrangement the coroutines environment uses, and the reason a plain HTTP request to the
 * dispatching route ends up in the task worker's onTask.
 *
 * requests is off so that the http worker handling that dispatch does not attach: the point of the
 * test is that the attach happens in the *other* process, the one that runs the handler.
 */

declare(strict_types=1);

use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Message\RunDummy;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension('swoole', [
        // Without a task worker there is nowhere for a task to go, and the dispatch fails with
        // "task method can't be executed without task worker" before any of this is reached. One is
        // enough, and makes the process that attaches unambiguous.
        'task_worker' => [
            'settings' => [
                'worker_count' => 1,
            ],
        ],
        'platform' => [
            'xdebug' => [
                'requests' => 'off',
                'workers' => false,
                'tasks' => true,
            ],
        ],
    ]);

    $containerConfigurator->extension('framework', [
        'messenger' => [
            'enabled' => true,
            'transports' => [
                'swoole' => 'swoole://task',
            ],
            'routing' => [
                RunDummy::class => 'swoole',
            ],
        ],
    ]);
};

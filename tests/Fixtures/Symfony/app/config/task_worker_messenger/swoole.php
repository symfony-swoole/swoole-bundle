<?php

/**
 * EXPERIMENTAL feature - a group of messenger consumers inside one task worker.
 *
 * Four consumers, one group, so one task worker process runs all four in coroutines of its own, and
 * all four receive through the one transport of the same queue (see messenger.php). This is the
 * workload a deployment reaches for when it moves its consumers out of their own containers and into
 * the server, and it is the one that puts the most pressure on what a worker shares: four message
 * handlings in flight at once, each with a transport, an entity manager, a connection and a unit of
 * work behind it.
 *
 * The memory limit is high enough never to be reached in a test. Its own behaviour - the group
 * recycling and the consumers starting afresh - is covered by the task_worker_commands environments,
 * and here it would only take the consumers away mid-run.
 */

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension('swoole', [
        'http_server' => [
            // As every other coroutines fixture does - the pooled error handler is what the coroutine
            // path expects, and without it the container has no symfony_error_handler to hand it.
            'exception_handler' => [
                'type' => 'symfony',
            ],
            // Nothing routes to it; it is here to hold the server up while the task worker works.
            'settings' => [
                'worker_count' => 1,
                // A repeating Timer::tick keeps a worker's reactor non-empty, and a worker whose
                // reactor never empties is only stopped when worker_max_wait_time expires - which is
                // the wait raised below so an in-flight message can finish.
                'worker_max_wait_time' => 10,
            ],
            'hmr' => [
                'enabled' => 'off',
            ],
        ],
        'task_worker' => [
            'services' => [
                'reset_handler' => true,
            ],
            // --sleep is how long a consumer waits before polling a queue it found empty, and the
            // default is a second. The test fills the queue only once every consumer has announced
            // itself, so that second would be a second of the batch being drained by whichever
            // consumers happened to wake first.
            'commands' => [
                [
                    'messenger:consume default --sleep=0.1 --memory-limit=512M',
                    'messenger:consume default --sleep=0.1 --memory-limit=512M',
                    'messenger:consume default --sleep=0.1 --memory-limit=512M',
                    'messenger:consume default --sleep=0.1 --memory-limit=512M',
                ],
            ],
        ],
        'platform' => [
            'coroutines' => [
                'enabled' => true,
                'max_concurrency' => 30,
                'max_service_instances' => 20,
                // One connection per consumer and room to spare. The default is lower than the number
                // of consumers, and a group that has to queue for a connection would be four consumers
                // taking turns rather than four running at once.
                'doctrine_processor_config' => [
                    'limits' => [
                        'default' => 12,
                    ],
                ],
            ],
        ],
    ]);
};

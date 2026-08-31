<?php

/**
 * Worker, coroutine and command on every log record.
 *
 * One http worker and one task worker, and the task worker's group holds two commands - which is the
 * case the whole feature exists for, since the process they share can say nothing about which of them
 * wrote a line.
 */

declare(strict_types=1);

use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Command\WorkerContextLoggingCommand;
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
                [
                    'test:worker-context:log alpha',
                    'test:worker-context:log beta',
                ],
            ],
        ],
        'platform' => [
            'logging' => [
                'worker_context' => true,
            ],
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

    $services->set(WorkerContextLoggingCommand::class);
};

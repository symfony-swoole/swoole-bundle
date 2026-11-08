<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

// One worker whose service pools keep at most two instances given back - ServicePoolIdleLimitTest counts what its
// DBAL connection pool leaves open on the database.
return static function (ContainerConfigurator $containerConfigurator): void {
    $parameters = $containerConfigurator->parameters();

    $parameters->set('env(WORKER_COUNT)', 1);

    $parameters->set('env(TASK_WORKER_COUNT)', 1);

    $parameters->set('env(REACTOR_COUNT)', 1);

    $containerConfigurator->extension('swoole', [
        'http_server' => [
            'exception_handler' => [
                'type' => 'symfony',
            ],
        ],
        'task_worker' => [
            'settings' => [
                'worker_count' => '%env(int:TASK_WORKER_COUNT)%',
            ],
        ],
        'platform' => [
            'coroutines' => [
                'enabled' => true,
                'max_concurrency' => 30,
                'max_service_instances' => 20,
                'max_idle_service_instances' => 2,
            ],
        ],
    ]);
};

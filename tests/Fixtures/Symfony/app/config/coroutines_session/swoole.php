<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $parameters = $containerConfigurator->parameters();

    $parameters->set('env(DATABASE_HOST)', 'db');

    $parameters->set('env(COOKIE_LIFETIME)', 1440);

    $parameters->set('env(WORKER_COUNT)', 1);

    $parameters->set('env(TASK_WORKER_COUNT)', 1);

    $parameters->set('env(REACTOR_COUNT)', 1);

    $containerConfigurator->extension('swoole', [
        'http_server' => [
            'api' => true,
            'static' => [
                'strategy' => 'advanced',
                'public_dir' => '%kernel.project_dir%/public',
            ],
            'exception_handler' => [
                'type' => 'symfony',
            ],
        ],
        'task_worker' => [
            'settings' => [
                'worker_count' => '%env(int:TASK_WORKER_COUNT)%',
            ],
            'services' => [
                'reset_handler' => true,
            ],
        ],
        'platform' => [
            'coroutines' => [
                'enabled' => true,
                'max_concurrency' => 30,
                'max_service_instances' => 20,
            ],
        ],
        'session' => [
            'max_data_bytes' => 4096,
            'max_active_sessions' => 100000,
        ],
    ]);
};

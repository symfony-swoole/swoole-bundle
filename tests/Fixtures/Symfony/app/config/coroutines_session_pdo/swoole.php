<?php

declare(strict_types=1);

use SwooleBundle\SwooleBundle\Bridge\Symfony\HttpFoundation\Session\PdoSession\PdoConnectionFactory;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\PdoSessionHandler;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

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
    ]);

    $services = $containerConfigurator->services();

    $services->defaults()
        ->autowire()
        ->autoconfigure();

    $services->set('swoole_bundle.session.pdo_handler.pdo', PDO::class)
        ->factory([PdoConnectionFactory::class, 'newInstanceFromDsnOrUrl'])
        ->arg('$dsnOrUrl', 'mysql://user:pass@%env(DATABASE_HOST)%:3306/db')
        ->tag('swoole_bundle.stateful_service');

    $services->set('swoole_bundle.session.pdo_handler', PdoSessionHandler::class)
        ->arg('$pdoOrDsn', service('swoole_bundle.session.pdo_handler.pdo'))
        ->arg(
            '$options',
            [
                'ttl' => '%env(int:COOKIE_LIFETIME)%',
                'db_table' => 'symfony_session',
                'db_id_col' => 'id',
                'db_data_col' => 'data',
                'db_time_col' => 'time',
                'db_lifetime_col' => 'lifetime',
            ],
        );
};

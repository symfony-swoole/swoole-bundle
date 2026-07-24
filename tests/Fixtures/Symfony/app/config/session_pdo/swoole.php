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

    $services = $containerConfigurator->services();

    $services->set('swoole_bundle.session.pdo_handler.pdo', PDO::class)
        ->factory([PdoConnectionFactory::class, 'newInstanceFromDsnOrUrl'])
        ->arg('$dsnOrUrl', 'mysql://user:pass@%env(DATABASE_HOST)%:3306/db');

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

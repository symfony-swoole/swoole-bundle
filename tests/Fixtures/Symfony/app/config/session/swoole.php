<?php

declare(strict_types=1);

use SwooleBundle\SwooleBundle\Bridge\Symfony\HttpFoundation\Session\SwooleSessionStorageFactory;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension('framework', [
        'session' => [
            'enabled' => true,
            'storage_factory_id' => 'swoole_bundle.session.table_storage_factory',
        ],
    ]);

    $parameters = $containerConfigurator->parameters();

    $parameters->set('env(COOKIE_LIFETIME)', 60);

    $services = $containerConfigurator->services();

    $services->defaults()
        ->autowire()
        ->autoconfigure();

    $services->set(SwooleSessionStorageFactory::class)
        ->arg('$lifetimeSeconds', '%env(int:COOKIE_LIFETIME)%');
};

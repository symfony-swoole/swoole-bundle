<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $parameters = $containerConfigurator->parameters();

    $parameters->set('env(COOKIE_LIFETIME)', 60);

    $containerConfigurator->extension('framework', [
        'cache' => [
            'app' => 'cache.adapter.array',
            'system' => 'cache.adapter.array',
        ],
        'session' => [
            'enabled' => true,
            'storage_factory_id' => 'swoole_bundle.session.table_storage_factory',
            'handler_id' => 'swoole_bundle.session.pdo_handler',
            'cookie_lifetime' => '%env(int:COOKIE_LIFETIME)%',
            // garbage collection may cause deadlocks in tests, so it's turned off
            'gc_probability' => 0,
            'gc_divisor' => 100,
        ],
    ]);
};

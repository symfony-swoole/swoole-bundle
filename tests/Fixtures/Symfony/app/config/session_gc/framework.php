<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $parameters = $containerConfigurator->parameters();

    $parameters->set('env(COOKIE_LIFETIME)', 60);
    $parameters->set('env(SESSION_GC_PROBABILITY)', '100');
    $parameters->set('env(SESSION_GC_DIVISOR)', '100');

    $containerConfigurator->extension('framework', [
        'session' => [
            'enabled' => true,
            'storage_factory_id' => 'swoole_bundle.session.table_storage_factory',
            'cookie_lifetime' => '%env(int:COOKIE_LIFETIME)%',
            'gc_probability' => '%env(int:SESSION_GC_PROBABILITY)%',
            'gc_divisor' => '%env(int:SESSION_GC_DIVISOR)%',
        ],
    ]);

    $containerConfigurator->extension('swoole', [
        'session' => [
            'max_data_bytes' => 512,
            'max_active_sessions' => 100,
        ],
    ]);
};

<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension('swoole', [
        'session' => [
            'max_data_bytes' => 2048,
            'max_active_sessions' => 500,
        ],
    ]);
    $containerConfigurator->extension('framework', [
        'session' => [
            'enabled' => true,
            'storage_factory_id' => 'swoole_bundle.session.table_storage_factory',
        ],
    ]);
};

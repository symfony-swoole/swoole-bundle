<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $parameters = $containerConfigurator->parameters();

    $parameters->set('swoole_bundle.cache_dir_name', 'swoole_bundle');

    $parameters->set('swoole_bundle.cache_dir', '%kernel.cache_dir%/%swoole_bundle.cache_dir_name%');

    $containerConfigurator->extension('swoole', [
        'http_server' => [
            'static' => 'auto',
            'hmr' => [
                'enabled' => 'external',
                'file_path' => '%swoole_bundle.cache_dir%',
            ],
        ],
    ]);
};

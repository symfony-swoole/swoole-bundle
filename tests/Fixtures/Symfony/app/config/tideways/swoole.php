<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension('swoole', [
        'http_server' => [
            'services' => [
                'tideways_apm' => [
                    'enabled' => true,
                    'service_name' => 'swoole_bundle_test',
                ],
            ],
        ],
    ]);
};

<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension('swoole', [
        'http_server' => [
            'healthcheck' => [
                'enabled' => true,
                'port' => 9997,
                'checks' => [
                    'interval' => 1,
                    'staleness_threshold' => 3,
                ],
            ],
            'settings' => [
                'worker_count' => 1,
            ],
        ],
    ]);
};

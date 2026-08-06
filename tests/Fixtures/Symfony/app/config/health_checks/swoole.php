<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    // Parallel feature tests move the health process onto a port of their own; ServerTestCase
    // exports the one the current worker owns.
    $containerConfigurator->parameters()->set('env(HEALTH_PORT)', 9997);

    $containerConfigurator->extension('swoole', [
        'http_server' => [
            'healthcheck' => [
                'enabled' => true,
                'port' => '%env(int:HEALTH_PORT)%',
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

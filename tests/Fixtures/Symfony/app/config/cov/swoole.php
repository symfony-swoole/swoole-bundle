<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension('swoole', [
        'http_server' => [
            'settings' => [
                'worker_count' => 1,
                'reactor_count' => 1,
            ],
        ],
    ]);
};

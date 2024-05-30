<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->defaults()
        ->autowire()
        ->autoconfigure();

    $containerConfigurator->extension('swoole', [
        'http_server' => [
            'settings' => [
                'user' => 'user_test',
                'group' => 'group_test',
            ],
        ],
    ]);
};

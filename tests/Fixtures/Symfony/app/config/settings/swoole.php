<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension('swoole', [
        'http_server' => [
            'settings' => [
                'upload_tmp_dir' => param('kernel.project_dir') . '/public/uploads',
            ],
        ],
    ]);

    $services = $containerConfigurator->services();

    $services->defaults()
        ->autowire()
        ->autoconfigure();
};

<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension('framework', [
        'secret' => 'ThisIsVeryNotSecret!',
        'default_locale' => 'en',
        'php_errors' => [
            'log' => true,
        ],
        'test' => null,
        'session' => [
            'enabled' => false,
        ],
        'router' => [
            'utf8' => true,
        ],
    ]);
};

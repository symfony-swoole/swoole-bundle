<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $parameters = $containerConfigurator->parameters();

    $parameters->set('env(DATABASE_HOST)', 'db');

    $containerConfigurator->extension('doctrine', [
        'dbal' => [
            'default_connection' => 'default',
            'connections' => [
                'default' => [
                    'driver' => 'pdo_mysql',
                    'charset' => 'utf8',
                    'user' => 'user',
                    'password' => 'pass',
                    'host' => '%env(DATABASE_HOST)%',
                    'port' => 3306,
                    'dbname' => 'db',
                ],
            ],
        ],
        'orm' => [
            'default_entity_manager' => 'default',
            'auto_generate_proxy_classes' => '%kernel.debug%',
            'entity_managers' => [
                'default' => [
                    'connection' => 'default',
                    'naming_strategy' => 'doctrine.orm.naming_strategy.underscore_number_aware',
                    'auto_mapping' => true,
                    'mappings' => [
                        'App' => [
                            'is_bundle' => false,
                            'type' => 'attribute',
                            'dir' => '%kernel.project_dir%/../TestBundle/Resources/mapping',
                            'prefix' => 'SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Entity',
                            'alias' => 'TestBundle',
                        ],
                    ],
                ],
            ],
        ],
    ]);
};

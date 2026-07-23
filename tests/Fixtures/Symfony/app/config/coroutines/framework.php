<?php

declare(strict_types=1);

use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Message\RunDummy;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Message\SleepAndAppend;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $parameters = $containerConfigurator->parameters();

    $parameters->set('env(COOKIE_LIFETIME)', 60);

    $containerConfigurator->extension('framework', [
        'cache' => [
            'app' => 'cache.adapter.array',
            'system' => 'cache.adapter.array',
        ],
        'session' => [
            'enabled' => true,
            'storage_factory_id' => 'swoole_bundle.session.table_storage_factory',
            'cookie_lifetime' => '%env(int:COOKIE_LIFETIME)%',
        ],
        'messenger' => [
            'enabled' => true,
            'transports' => [
                'swoole' => 'swoole://task',
            ],
            'routing' => [
                SleepAndAppend::class => 'swoole',
                RunDummy::class => 'swoole',
            ],
        ],
    ]);
};

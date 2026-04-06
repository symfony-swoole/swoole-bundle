<?php

declare(strict_types=1);

use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Message\RunDummy;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Message\SleepAndAppend;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension('framework', [
        'cache' => [
            'app' => 'cache.adapter.array',
            'system' => 'cache.adapter.array',
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

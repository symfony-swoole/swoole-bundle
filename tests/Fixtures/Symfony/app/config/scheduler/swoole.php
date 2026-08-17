<?php

declare(strict_types=1);

use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\MessageHandler\CreateFileMessageHandler;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension('framework', [
        'messenger' => [
            'enabled' => true,
        ],
    ]);

    $containerConfigurator->extension('swoole', [
        'http_server' => [
            'services' => [
                'scheduler' => [
                    'enabled' => true,
                    // The default is 60 seconds - overridden here so the test doesn't have to
                    // wait a minute for the first tick.
                    'interval' => 1,
                ],
            ],
        ],
    ]);

    $services = $containerConfigurator->services();

    $services->defaults()
        ->autowire()
        ->autoconfigure();

    $services->set(CreateFileMessageHandler::class)
        ->tag('messenger.message_handler');
};

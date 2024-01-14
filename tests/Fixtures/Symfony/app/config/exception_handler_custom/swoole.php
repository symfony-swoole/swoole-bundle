<?php

declare(strict_types=1);

use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\ExceptionHandler\TestCustomExceptionHandler;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension('swoole', [
        'http_server' => [
            'exception_handler' => [
                'type' => 'custom',
                'handler_id' => 'test_bundle.custom.exception_handler',
            ],
        ],
    ]);

    $services = $containerConfigurator->services();

    $services->defaults()
        ->autowire()
        ->autoconfigure()
    ;

    $services->alias('test_bundle.custom.exception_handler', TestCustomExceptionHandler::class);

    $services->set(TestCustomExceptionHandler::class);
};

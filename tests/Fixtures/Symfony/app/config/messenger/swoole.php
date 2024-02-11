<?php

declare(strict_types=1);

use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Controller\TaskController;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Message\CreateFileMessage;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\MessageHandler\CreateFileMessageHandler;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension('framework', [
        'messenger' => [
            'enabled' => true,
            'transports' => [
                'swoole' => 'swoole://task',
            ],
            'routing' => [
                CreateFileMessage::class => 'swoole',
            ],
        ],
    ]);

    $containerConfigurator->extension('swoole', [
        'task_worker' => [
            'settings' => [
                'worker_count' => 'auto',
            ],
        ],
    ]);

    $services = $containerConfigurator->services();

    $services->defaults()
        ->autowire()
        ->autoconfigure();

    $services->set(TaskController::class)
        ->tag('controller.service_arguments');

    $services->set(CreateFileMessageHandler::class)
        ->tag('messenger.message_handler');
};

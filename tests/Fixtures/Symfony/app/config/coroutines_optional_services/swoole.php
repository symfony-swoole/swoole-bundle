<?php

declare(strict_types=1);

use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Service\InterfaceSluggerFactory;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\String\Slugger\SluggerInterface;

return static function (ContainerConfigurator $containerConfigurator): void {
    $parameters = $containerConfigurator->parameters();

    $parameters->set('env(WORKER_COUNT)', 1);

    $parameters->set('env(TASK_WORKER_COUNT)', 1);

    $parameters->set('env(REACTOR_COUNT)', 1);

    $containerConfigurator->extension('swoole', [
        'http_server' => [
            'exception_handler' => [
                'type' => 'symfony',
            ],
        ],
        'task_worker' => [
            'settings' => [
                'worker_count' => '%env(int:TASK_WORKER_COUNT)%',
            ],
        ],
        'platform' => [
            'coroutines' => [
                'enabled' => true,
                'max_concurrency' => 30,
                'max_service_instances' => 20,
            ],
        ],
    ]);

    $services = $containerConfigurator->services();

    // The "slugger" service is an optional service to proxify. Redefining it with an interface as its definition
    // class must make the stateful services compiler pass skip its proxification instead of failing.
    $services->set('slugger', SluggerInterface::class)
        ->factory([InterfaceSluggerFactory::class, 'newSlugger']);
};

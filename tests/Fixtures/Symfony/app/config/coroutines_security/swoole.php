<?php

declare(strict_types=1);

use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Command\{
    SecurityFirewallEventDispatcherProxyCheckCommand,
};
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $parameters = $containerConfigurator->parameters();

    $parameters->set('env(WORKER_COUNT)', 1);

    $parameters->set('env(TASK_WORKER_COUNT)', 1);

    $parameters->set('env(REACTOR_COUNT)', 1);

    // Without a Doctrine-registered cache pool config to resolve it indirectly, cache.system's
    // definition stays the bare AdapterInterface, which StatefulServicesPass cannot proxify (see the
    // ProxifierAssertions warning suggesting exactly this override).
    $containerConfigurator->extension('framework', [
        'cache' => [
            'system' => 'cache.adapter.filesystem',
        ],
    ]);

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

    $services->defaults()
        ->autowire()
        ->autoconfigure();

    // depends on security.event_dispatcher.main/api, only registered here (see security.php), so it
    // stays excluded from the global TestBundle/* autoload in ../services.php.
    $services->set(SecurityFirewallEventDispatcherProxyCheckCommand::class);
};

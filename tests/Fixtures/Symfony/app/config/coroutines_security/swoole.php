<?php

declare(strict_types=1);

use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Command\{
    AccessDecisionManagerProxyCheckCommand,
    SecurityFirewallEventDispatcherProxyCheckCommand,
};
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

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

    $services->defaults()
        ->autowire()
        ->autoconfigure();

    // depends on security.event_dispatcher.main/api, only registered here (see security.php), so it
    // stays excluded from the global TestBundle/* autoload in ../services.php.
    $services->set(SecurityFirewallEventDispatcherProxyCheckCommand::class);

    // depends on security.access.decision_manager, which only exists where SecurityBundle is registered
    // - here - so it stays excluded from the global TestBundle/* autoload in ../services.php too.
    $services->set(AccessDecisionManagerProxyCheckCommand::class);
};

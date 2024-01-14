<?php

declare(strict_types=1);

use K911\Swoole\Bridge\Symfony\Bundle\Command\ServerProfileCommand;
use K911\Swoole\Bridge\Symfony\Bundle\Command\ServerReloadCommand;
use K911\Swoole\Bridge\Symfony\Bundle\Command\ServerRunCommand;
use K911\Swoole\Bridge\Symfony\Bundle\Command\ServerStartCommand;
use K911\Swoole\Bridge\Symfony\Bundle\Command\ServerStatusCommand;
use K911\Swoole\Bridge\Symfony\Bundle\Command\ServerStopCommand;
use K911\Swoole\Metrics\MetricsProvider;
use K911\Swoole\Server\Api\ApiServerClientFactory;
use K911\Swoole\Server\Config\Sockets;
use K911\Swoole\Server\Configurator\CallableChainConfigurator;
use K911\Swoole\Server\Configurator\CallableChainConfiguratorFactory;
use K911\Swoole\Server\HttpServer;
use K911\Swoole\Server\HttpServerConfiguration;
use K911\Swoole\Server\Runtime\BootableInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->defaults();

    $services->set(ServerStatusCommand::class)
        ->arg('$sockets', service(Sockets::class))
        ->arg('$apiServerClientFactory', service(ApiServerClientFactory::class))
        ->arg('$metricsProvider', service(MetricsProvider::class))
        ->arg('$parameterBag', service('parameter_bag'))
        ->tag('console.command', [
            'command' => 'swoole:server:status',
        ])
    ;

    $services->set(ServerStopCommand::class)
        ->arg('$server', service(HttpServer::class))
        ->arg('$serverConfiguration', service(HttpServerConfiguration::class))
        ->arg('$parameterBag', service('parameter_bag'))
        ->tag('console.command', [
            'command' => 'swoole:server:stop',
        ])
    ;

    $services->set(ServerReloadCommand::class)
        ->arg('$server', service(HttpServer::class))
        ->arg('$serverConfiguration', service(HttpServerConfiguration::class))
        ->arg('$parameterBag', service('parameter_bag'))
        ->tag('console.command', [
            'command' => 'swoole:server:reload',
        ])
    ;

    $services->set('swoole_bundle.server.http_server.configurator.for_server_start_command', CallableChainConfigurator::class)
        ->factory([
            service(CallableChainConfiguratorFactory::class),
            'make',
        ])
        ->args([
            service('swoole_bundle.server.http_server.configurator_collection'),
            service('swoole_bundle.server.http_server.configurator.with_request_handler'),
        ])
    ;

    $services->set(ServerStartCommand::class)
        ->arg('$server', service(HttpServer::class))
        ->arg('$serverConfiguration', service(HttpServerConfiguration::class))
        ->arg('$serverConfigurator', service('swoole_bundle.server.http_server.configurator.for_server_start_command'))
        ->arg('$parameterBag', service('parameter_bag'))
        ->arg('$bootManager', service(BootableInterface::class))
        ->tag('console.command', [
            'command' => 'swoole:server:start',
        ])
    ;

    $services->set('swoole_bundle.server.http_server.configurator.for_server_run_command', CallableChainConfigurator::class)
        ->factory([
            service(CallableChainConfiguratorFactory::class),
            'make',
        ])
        ->args([
            service('swoole_bundle.server.http_server.configurator_collection'),
            service('swoole_bundle.server.http_server.configurator.with_request_handler'),
            service('swoole_bundle.server.http_server.configurator.default_handler'),
        ])
    ;

    $services->set(ServerRunCommand::class)
        ->arg('$server', service(HttpServer::class))
        ->arg('$serverConfiguration', service(HttpServerConfiguration::class))
        ->arg('$serverConfigurator', service('swoole_bundle.server.http_server.configurator.for_server_run_command'))
        ->arg('$parameterBag', service('parameter_bag'))
        ->arg('$bootManager', service(BootableInterface::class))
        ->tag('console.command', [
            'command' => 'swoole:server:run',
        ])
    ;

    $services->set('swoole_bundle.server.http_server.configurator.for_server_profile_command', CallableChainConfigurator::class)
        ->factory([
            service(CallableChainConfiguratorFactory::class),
            'make',
        ])
        ->args([
            service('swoole_bundle.server.http_server.configurator_collection'),
            service('swoole_bundle.server.http_server.configurator.with_limited_request_handler'),
            service('swoole_bundle.server.http_server.configurator.default_handler'),
        ])
    ;

    $services->set(ServerProfileCommand::class)
        ->arg('$server', service(HttpServer::class))
        ->arg('$serverConfiguration', service(HttpServerConfiguration::class))
        ->arg('$serverConfigurator', service('swoole_bundle.server.http_server.configurator.for_server_profile_command'))
        ->arg('$parameterBag', service('parameter_bag'))
        ->arg('$bootManager', service(BootableInterface::class))
        ->tag('console.command', [
            'command' => 'swoole:server:profile',
        ])
    ;
};

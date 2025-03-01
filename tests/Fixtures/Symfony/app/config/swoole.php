<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $parameters = $containerConfigurator->parameters();

    $parameters->set('env(PORT)', 9501);

    $parameters->set('env(HOST)', '0.0.0.0');

    $parameters->set('env(TRUSTED_HOSTS)', 'localhost,127.0.0.1,.*.swoole-bundle.orb.local,192.168.*.*');

    $parameters->set('env(TRUSTED_PROXIES)', '*,192.168.0.0/16');

    $parameters->set('env(WORKER_COUNT)', 6);

    $parameters->set('env(REACTOR_COUNT)', 3);

    $containerConfigurator->extension('swoole', [
        'http_server' => [
            'port' => '%env(int:PORT)%',
            'host' => '%env(HOST)%',
            'trusted_hosts' => '%env(TRUSTED_HOSTS)%',
            'trusted_proxies' => '%env(TRUSTED_PROXIES)%',
            'settings' => [
                'worker_count' => '%env(int:WORKER_COUNT)%',
                'reactor_count' => '%env(int:REACTOR_COUNT)%',
            ],
        ],
    ]);
};

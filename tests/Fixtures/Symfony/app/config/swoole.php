<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $parameters = $containerConfigurator->parameters();

    $parameters->set('env(PORT)', 9501);

    $parameters->set('env(HOST)', '0.0.0.0');

    $parameters->set('env(TRUSTED_HOSTS)', 'localhost,127.0.0.1,.*.swoole-bundle.orb.local,192.168.*.*');

    $parameters->set('env(TRUSTED_PROXIES)', '*,192.168.0.0/16');

    // Two is what it takes to be a multi-worker server, which is all any test that does not say
    // otherwise is asking for - and the count is what the suite can least afford to be generous with.
    // Every server here costs its worker count in processes, so at six a single test held ten, and four
    // test workers on a four-core build box held forty. Servers then started and died before a client
    // could reach them, which the tests reported as "the server was started but never answered". At two
    // the same four workers run green and finish sooner than three workers did with six.
    //
    // The coroutines environments set their own - one worker, one task worker, one reactor - since a
    // coroutine server has no use for more.
    $parameters->set('env(WORKER_COUNT)', 2);

    $parameters->set('env(REACTOR_COUNT)', 1);

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

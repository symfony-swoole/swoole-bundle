<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    /**
     * A server run under swoole:server:watch, for the restart that has to clear the cache first.
     *
     * An environment of its own for the same reason hmr_restart has one: the test makes this container
     * stale on purpose, by touching the file you are reading. A shared environment's config would make
     * every other worker's server stale at the same time.
     *
     * HMR off. The supervisor is what restarts here, and an HMR timer in the workers would only add a
     * second thing reacting to the same edit while the test is deciding what the first one did.
     *
     * @see \SwooleBundle\SwooleBundle\Tests\Feature\SwooleServerWatchCacheClearTest
     */
    $containerConfigurator->extension('swoole', [
        'http_server' => [
            'hmr' => [
                'enabled' => 'off',
            ],
            'settings' => [
                'worker_count' => 1,
            ],
        ],
    ]);
};

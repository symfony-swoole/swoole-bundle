<?php

/**
 * Requests attach, nothing else does.
 *
 * One attach point per environment, and that is not tidiness: a client that has attached reports
 * itself attached, so whichever handler gets there first makes every later one a no-op in that
 * process. With workers on, an http worker would already be attached before it ever saw a request,
 * and the request handler under test here would never do anything.
 */

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension('swoole', [
        'platform' => [
            'xdebug' => [
                'requests' => 'trigger',
                'workers' => false,
                'tasks' => false,
            ],
        ],
    ]);
};

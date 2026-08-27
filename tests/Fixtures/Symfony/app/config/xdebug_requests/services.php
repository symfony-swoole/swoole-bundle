<?php

/**
 * Puts a recording client behind the attach handlers, in place of the one that talks to xdebug.
 *
 * The bundle aliases XdebugClient to its native implementation, so overriding the alias is all it
 * takes - the handlers reference the alias and are left exactly as an application runs them.
 *
 * The record file lives under this worker's var directory (test.var_dir, not a hardcoded "var", so
 * parallel test workers do not read each other's attaches) and is written by every process the server
 * forks.
 */

declare(strict_types=1);

use SwooleBundle\SwooleBundle\Bridge\Xdebug\XdebugClient;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Xdebug\RecordingXdebugClient;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(RecordingXdebugClient::class)
        ->arg('$recordFile', '%test.var_dir%/log/xdebug-attaches.log')
        ->public();

    $services->alias(XdebugClient::class, RecordingXdebugClient::class);
};

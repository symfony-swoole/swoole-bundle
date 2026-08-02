<?php

/**
 * Two bare firewalls (no authenticators, no providers needed) purely so SecurityBundle builds a real
 * security.event_dispatcher.<name> per firewall (see SecurityExtension::createFirewall()) — that's the
 * definition EventDispatcherProcessor has to make coroutine-safe. Left to Symfony to build entirely; the
 * fixture only supplies the minimum config needed to make it exist.
 */

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension('security', [
        'firewalls' => [
            'main' => [
                'pattern' => '^/security/main',
                'security' => true,
                'stateless' => true,
            ],
            'api' => [
                'pattern' => '^/security/api',
                'security' => true,
                'stateless' => true,
            ],
        ],
    ]);
};

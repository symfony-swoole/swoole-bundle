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
        'providers' => [
            'in_memory' => [
                'memory' => [
                    'users' => [
                        'somebody' => ['password' => 'irrelevant', 'roles' => ['ROLE_USER']],
                    ],
                ],
            ],
        ],
        'password_hashers' => [
            'Symfony\Component\Security\Core\User\InMemoryUser' => 'plaintext',
        ],
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
            // stateful and lazy, which is what makes SecurityBundle build a LazyFirewallContext and give
            // it a ContextListener - two of the services SecurityProcessor has to make worker-safe. The
            // stateless firewalls above get neither. The authenticator brings the third: with debug on,
            // SecurityBundle wraps it in a TraceableAuthenticator that records what it made of the
            // request on itself.
            'session' => [
                'pattern' => '^/security/session',
                'security' => true,
                'stateless' => false,
                'lazy' => true,
                'provider' => 'in_memory',
                'http_basic' => true,
            ],
        ],
    ]);
};

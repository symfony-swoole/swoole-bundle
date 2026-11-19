<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass;

use SwooleBundle\SwooleBundle\Bridge\Symfony\HttpFoundation\Session\SecureSessionCookieListener;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * Registers SecureSessionCookieListener when the application has sessions and leaves their cookie's Secure flag
 * to the request (`framework.session.cookie_secure: auto`, Symfony's default).
 *
 * Decided here rather than in the bundle's extension: `session.storage.options` is FrameworkExtension's, and is
 * there only once every extension has loaded.
 */
final class SecureSessionCookiePass implements CompilerPassInterface
{
    public const string LISTENER_SERVICE_ID = 'swoole_bundle.session.secure_cookie_listener';

    public function process(ContainerBuilder $container): void
    {
        // An alias of test.session.listener when framework.test is on, so has() rather than hasDefinition().
        if (!$container->has('session_listener') || !$container->hasParameter('session.storage.options')) {
            return;
        }

        $options = $container->getParameter('session.storage.options');

        if (!is_array($options) || ($options['cookie_secure'] ?? null) !== 'auto') {
            return;
        }

        $container->setDefinition(
            self::LISTENER_SERVICE_ID,
            (new Definition(SecureSessionCookieListener::class))
                ->addTag('kernel.event_subscriber'),
        );
    }
}

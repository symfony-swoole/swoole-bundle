<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\HttpFoundation\Session;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Makes the session cookie Secure on a request that came over HTTPS, which is what
 * `framework.session.cookie_secure: auto` (Symfony's default) promises and what nothing else delivers here.
 *
 * Symfony's session listener writes the session cookie onto the response, and with `auto` it takes the
 * Secure flag from PHP's `session.cookie_secure` ini setting. Symfony's own native storage factory is what
 * sets that setting, with `ini_set()`, when it sees a secure request. This bundle's storage factories do not,
 * and must not: an ini setting belongs to the whole worker, which serves many requests one after another and,
 * with coroutines, many at once. Set once, it would stay set for every plain-HTTP request after it.
 * So the cookie went out without Secure over HTTPS too.
 *
 * This runs after the session listener (-1000) and before the streamed response listener (-1024), which sends
 * a streamed response's headers, and decides by the request in hand. It is registered only when cookie_secure
 * is `auto`: with true or false, the session listener already writes what was configured.
 *
 * @see \Symfony\Component\HttpKernel\EventListener\AbstractSessionListener::onKernelResponse()
 * @see \Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorageFactory::createStorage()
 */
final readonly class SecureSessionCookieListener implements EventSubscriberInterface
{
    public const int PRIORITY = -1010;

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        // A session nothing used has not written a cookie either.
        if (!$request->isSecure() || !$request->hasSession(true)) {
            return;
        }

        $sessionName = $request->getSession()->getName();
        $headers = $event->getResponse()->headers;

        foreach ($headers->getCookies() as $cookie) {
            if ($cookie->getName() !== $sessionName || $cookie->isSecure()) {
                continue;
            }

            // Kept by name, path and domain, so this replaces the cookie rather than adding a second one.
            $headers->setCookie($cookie->withSecure());
        }
    }

    /**
     * {@inheritDoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onKernelResponse', self::PRIORITY],
        ];
    }
}

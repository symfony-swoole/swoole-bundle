<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\HttpFoundation\Session;

use SwooleBundle\SwooleBundle\Bridge\Symfony\Event\RequestWithSessionFinishedEvent;
use SwooleBundle\SwooleBundle\Server\Session\Storage;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Event\FinishRequestEvent;
use Symfony\Component\HttpKernel\Event\KernelEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Sets the session in the request.
 */
final class SessionCookieEventListener implements EventSubscriberInterface
{
    /**
     * @var array{
     *   domain: string,
     *   httponly: bool,
     *   lifetime: int,
     *   path: string,
     *   secure: bool|null,
     *   samesite: ''|'lax'|'none'|'strict'|null
     * }
     */
    private array $sessionCookieParameters;

    /**
     * @param array{
     *    cookie_domain?: string,
     *    cookie_httponly?: bool,
     *    cookie_lifetime?: int,
     *    cookie_path?: string,
     *    cookie_secure?: bool|null,
     *    cookie_samesite?: ''|'lax'|'none'|'strict'|null
     *  } $sessionOptions
     */
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly Storage $swooleStorage,
        array $sessionOptions = [],
    ) {
        $this->sessionCookieParameters = $this->mergeCookieParams($sessionOptions);
    }

    public function onFinishRequest(FinishRequestEvent $event): void
    {
        if (!$event->isMainRequest() || !$this->isSessionRelated($event)) {
            return;
        }

        if ($this->session()->isStarted()) {
            $this->dispatcher->dispatch(
                new RequestWithSessionFinishedEvent($this->session()->getId()),
                RequestWithSessionFinishedEvent::NAME
            );
        }

        $this->swooleStorage->garbageCollect();
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest() || !$this->isSessionRelated($event)) {
            return;
        }

        $cookies = $event->getRequest()->cookies;
        $sessionName = $this->session()->getName();

        if (!$cookies->has($sessionName)) {
            return;
        }

        $sessionId = (string) $cookies->get($sessionName);
        $this->session()->setId($sessionId);
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest() || !$this->isSessionRelated($event)) {
            return;
        }

        $session = $event->getRequest()->getSession();
        if (!$session->isStarted()) {
            return;
        }

        $responseHeaderBag = $event->getResponse()->headers;
        $cookie = $this->findSessionCookie($responseHeaderBag, $session->getName());

        if ($cookie !== null) {
            return;
        }

        $responseHeaderBag->setCookie($this->makeSessionCookie($session));
    }

    /**
     * {@inheritDoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 192],
            KernelEvents::RESPONSE => ['onKernelResponse', -128],
            KernelEvents::FINISH_REQUEST => ['onFinishRequest', -128],
        ];
    }

    private function findSessionCookie(ResponseHeaderBag $headers, string $sessionName): ?Cookie
    {
        foreach ($headers->getCookies() as $cookie) {
            if ($this->isSessionCookie($cookie, $sessionName)) {
                return $cookie;
            }
        }

        return null;
    }

    private function isSessionCookie(Cookie $cookie, string $sessionName): bool
    {
        return $this->sessionCookieParameters['path'] === $cookie->getPath()
            && $this->sessionCookieParameters['domain'] === $cookie->getDomain()
            && $sessionName === $cookie->getName();
    }

    private function makeSessionCookie(SessionInterface $session): Cookie
    {
        return new Cookie(
            $session->getName(),
            $session->getId(),
            $this->sessionCookieParameters['lifetime'] === 0 ? 0 : time() + $this->sessionCookieParameters['lifetime'],
            $this->sessionCookieParameters['path'],
            $this->sessionCookieParameters['domain'],
            (bool) $this->sessionCookieParameters['secure'],
            $this->sessionCookieParameters['httponly'],
            false,
            $this->sessionCookieParameters['samesite']
        );
    }

    /**
     * @param array{
     *   cookie_domain?: string,
     *   cookie_httponly?: bool,
     *   cookie_lifetime?: int,
     *   cookie_path?: string,
     *   cookie_secure?: bool|null,
     *   cookie_samesite?: ''|'lax'|'none'|'strict'|null
     * } $sessionOptions
     * @return array{
     *    domain: string,
     *    httponly: bool,
     *    lifetime: int,
     *    path: string,
     *    secure: bool,
     *    samesite: ''|'lax'|'none'|'strict'|null
     *  }
     */
    private function mergeCookieParams(array $sessionOptions): array
    {
        $params = session_get_cookie_params() + ['samesite' => null];
        foreach ($sessionOptions as $k => $v) {
            if (mb_strpos($k, 'cookie_') !== 0) {
                continue;
            }

            $params[mb_substr($k, 7)] = $v;
        }

        return $params; /** @phpstan-ignore-line */ // phpcs:ignore
    }

    private function isSessionRelated(KernelEvent $event): bool
    {
        return $event->getRequest()->hasSession();
    }

    private function session(): SessionInterface
    {
        return $this->requestStack->getSession();
    }
}

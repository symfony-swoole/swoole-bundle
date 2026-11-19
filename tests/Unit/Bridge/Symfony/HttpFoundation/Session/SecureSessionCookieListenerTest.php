<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Symfony\HttpFoundation\Session;

use PHPUnit\Framework\TestCase;
use SwooleBundle\SwooleBundle\Bridge\Symfony\HttpFoundation\Session\SecureSessionCookieListener;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

final class SecureSessionCookieListenerTest extends TestCase
{
    private const string SESSION_NAME = 'SWOOLESSID';

    public function testTheSessionCookieOfAnHttpsRequestIsSecure(): void
    {
        $response = $this->respond(Request::create('https://example.com/'));

        $cookie = $this->cookieNamed($response, self::SESSION_NAME);
        $this->assertTrue($cookie->isSecure());
        // Everything else the session listener wrote is kept.
        $this->assertSame('abc', $cookie->getValue());
        $this->assertSame('/', $cookie->getPath());
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame(Cookie::SAMESITE_LAX, $cookie->getSameSite());
        $this->assertCount(2, $response->headers->getCookies(), 'Replaced, not added beside the old one.');
    }

    public function testTheSessionCookieOfAPlainHttpRequestStaysAsItWas(): void
    {
        $response = $this->respond(Request::create('http://localhost/'));

        $this->assertFalse($this->cookieNamed($response, self::SESSION_NAME)->isSecure());
    }

    public function testARequestForwardedAsHttpsByATrustedProxyIsSecure(): void
    {
        $request = Request::create('http://app.example.com/', server: ['REMOTE_ADDR' => '10.0.0.1']);
        $request->headers->set('X-Forwarded-Proto', 'https');
        $trustedProxies = Request::getTrustedProxies();
        $trustedHeaderSet = Request::getTrustedHeaderSet();
        Request::setTrustedProxies(['10.0.0.0/8'], Request::HEADER_X_FORWARDED_PROTO);

        try {
            $response = $this->respond($request);
        } finally {
            Request::setTrustedProxies($trustedProxies, $trustedHeaderSet);
        }

        $this->assertTrue($this->cookieNamed($response, self::SESSION_NAME)->isSecure());
    }

    public function testOtherCookiesAreLeftAlone(): void
    {
        $response = $this->respond(Request::create('https://example.com/'));

        $this->assertFalse($this->cookieNamed($response, 'other')->isSecure());
    }

    public function testASubRequestIsLeftAlone(): void
    {
        $response = $this->respond(Request::create('https://example.com/'), HttpKernelInterface::SUB_REQUEST);

        $this->assertFalse($this->cookieNamed($response, self::SESSION_NAME)->isSecure());
    }

    public function testARequestWithoutASessionIsLeftAlone(): void
    {
        $request = Request::create('https://example.com/');
        $response = new Response();
        $response->headers->setCookie(Cookie::create(self::SESSION_NAME, 'abc'));

        (new SecureSessionCookieListener())->onKernelResponse(
            new ResponseEvent(
                $this->createStub(HttpKernelInterface::class),
                $request,
                HttpKernelInterface::MAIN_REQUEST,
                $response,
            ),
        );

        $this->assertFalse($this->cookieNamed($response, self::SESSION_NAME)->isSecure());
    }

    public function testItRunsAfterTheSessionListenerAndBeforeStreamedResponsesSendTheirHeaders(): void
    {
        $subscribed = SecureSessionCookieListener::getSubscribedEvents()[KernelEvents::RESPONSE];
        $this->assertIsArray($subscribed);
        $this->assertLessThan(-1000, $subscribed[1]);
        $this->assertGreaterThan(-1024, $subscribed[1]);
    }

    private function respond(Request $request, int $requestType = HttpKernelInterface::MAIN_REQUEST): Response
    {
        $request->setSession(new Session(new MockArraySessionStorage(self::SESSION_NAME)));

        // What Symfony's session listener writes with cookie_secure: auto and PHP's default ini.
        $response = new Response();
        $response->headers->setCookie(
            Cookie::create(self::SESSION_NAME, 'abc', 0, '/', null, false, true, false, Cookie::SAMESITE_LAX),
        );
        $response->headers->setCookie(Cookie::create('other', 'x'));

        (new SecureSessionCookieListener())->onKernelResponse(
            new ResponseEvent($this->createStub(HttpKernelInterface::class), $request, $requestType, $response),
        );

        return $response;
    }

    private function cookieNamed(Response $response, string $name): Cookie
    {
        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() === $name) {
                return $cookie;
            }
        }

        $this->fail(sprintf('No cookie named %s.', $name));
    }
}

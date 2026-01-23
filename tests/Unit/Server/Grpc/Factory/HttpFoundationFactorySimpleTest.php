<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Server\Grpc\Factory;

use PHPUnit\Framework\TestCase;
use SwooleBundle\SwooleBundle\Server\Grpc\Factory\HttpFoundationFactory;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response as HttpFoundationResponse;

/**
 * Simplified test for HttpFoundationFactory that tests the response conversion
 * without needing the protobuf generated classes.
 */
final class HttpFoundationFactorySimpleTest extends TestCase
{
    private HttpFoundationFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new HttpFoundationFactory();
    }

    public function testConvertResponseSimple(): void
    {
        $httpResponse = new HttpFoundationResponse(
            'Hello World',
            200,
            ['Content-Type' => 'text/plain']
        );

        $psr7Response = $this->factory->convertResponse($httpResponse);

        self::assertSame(200, $psr7Response->getStatusCode());
        self::assertSame('Hello World', $psr7Response->getBody());
        self::assertSame('1.0', $psr7Response->getProtocolVersion());
    }

    public function testConvertResponseWithMultipleHeaders(): void
    {
        $httpResponse = new HttpFoundationResponse(
            'OK',
            200,
            [
                'Content-Type' => 'application/json',
                'X-Custom-Header' => 'custom-value',
                'Cache-Control' => 'no-cache, no-store',
            ]
        );

        $psr7Response = $this->factory->convertResponse($httpResponse);

        $headers = $psr7Response->getHeaders();

        // MapField can be checked with offsetExists
        self::assertTrue(isset($headers['Content-Type']));
        self::assertTrue(isset($headers['X-Custom-Header']));
        self::assertTrue(isset($headers['Cache-Control']));
    }

    public function testConvertResponseWithJsonContent(): void
    {
        $data = ['status' => 'success', 'message' => 'OK'];
        $httpResponse = new HttpFoundationResponse(
            json_encode($data),
            200,
            ['Content-Type' => 'application/json']
        );

        $psr7Response = $this->factory->convertResponse($httpResponse);

        self::assertSame(json_encode($data), $psr7Response->getBody());
        self::assertSame(200, $psr7Response->getStatusCode());
    }

    public function testConvertResponseWithCookies(): void
    {
        $httpResponse = new HttpFoundationResponse('OK', 200);
        $httpResponse->headers->setCookie(
            new Cookie('session', 'abc123', 0, '/', null, false, true)
        );

        $psr7Response = $this->factory->convertResponse($httpResponse);

        $headers = $psr7Response->getHeaders();
        self::assertTrue(isset($headers['Set-Cookie']));

        $setCookieHeader = $headers['Set-Cookie'];
        $values = iterator_to_array($setCookieHeader->getValue());
        self::assertNotEmpty($values);
        self::assertStringContainsString('session=abc123', $values[0]);
    }

    public function testConvertResponse404(): void
    {
        $httpResponse = new HttpFoundationResponse('Not Found', 404);

        $psr7Response = $this->factory->convertResponse($httpResponse);

        self::assertSame(404, $psr7Response->getStatusCode());
        self::assertSame('Not Found', $psr7Response->getBody());
    }

    public function testConvertResponseWithEmptyBody(): void
    {
        $httpResponse = new HttpFoundationResponse('', 204);

        $psr7Response = $this->factory->convertResponse($httpResponse);

        self::assertSame(204, $psr7Response->getStatusCode());
        self::assertSame('', $psr7Response->getBody());
    }

    public function testConvertResponseWithPlaceholderReasonPhrase(): void
    {
        $httpResponse = new HttpFoundationResponse('OK', 200);
        // Simulate a response with a placeholder value for reason-phrase
        $httpResponse->headers->set('reason-phrase', '-');

        $psr7Response = $this->factory->convertResponse($httpResponse);

        // Should convert '-' to empty string to avoid protobuf errors
        self::assertSame('', $psr7Response->getReasonPhrase());
        self::assertSame(200, $psr7Response->getStatusCode());
    }
}

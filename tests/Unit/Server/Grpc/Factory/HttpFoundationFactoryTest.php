<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Server\Grpc\Factory;

use PHPUnit\Framework\TestCase;
use SwooleBundle\SwooleBundle\Server\Grpc\Factory\HttpFoundationFactory;
use SwooleBundle\SwooleBundle\Server\Grpc\Generated\HeaderValue;
use SwooleBundle\SwooleBundle\Server\Grpc\Generated\Psr7Request;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response as HttpFoundationResponse;

final class HttpFoundationFactoryTest extends TestCase
{
    private HttpFoundationFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new HttpFoundationFactory();
    }

    public function testMakeSimpleGetRequest(): void
    {
        $psr7Request = new Psr7Request();
        $psr7Request->setMethod('GET');
        $psr7Request->setUri('http://example.com/api/users');
        $psr7Request->setProtocolVersion('HTTP/1.1');

        $httpRequest = $this->factory->make($psr7Request);

        self::assertSame('GET', $httpRequest->getMethod());
        self::assertSame('http://example.com/api/users', $httpRequest->getUri());
        self::assertSame('HTTP/1.1', $httpRequest->server->get('SERVER_PROTOCOL'));
        self::assertSame('example.com', $httpRequest->server->get('SERVER_NAME'));
        self::assertEquals(80, $httpRequest->server->get('SERVER_PORT')); // Can be int or string
        self::assertSame('http', $httpRequest->server->get('REQUEST_SCHEME'));
    }

    public function testMakeRequestWithQueryString(): void
    {
        $psr7Request = new Psr7Request();
        $psr7Request->setMethod('GET');
        $psr7Request->setUri('https://api.example.com/search?q=test&limit=10');
        $psr7Request->setProtocolVersion('HTTP/2');

        $httpRequest = $this->factory->make($psr7Request);

        self::assertSame('q=test&limit=10', $httpRequest->server->get('QUERY_STRING'));
        self::assertSame('test', $httpRequest->query->get('q'));
        self::assertSame('10', $httpRequest->query->get('limit'));
    }

    public function testMakeHttpsRequest(): void
    {
        $psr7Request = new Psr7Request();
        $psr7Request->setMethod('GET');
        $psr7Request->setUri('https://secure.example.com/api');

        $httpRequest = $this->factory->make($psr7Request);

        self::assertSame('on', $httpRequest->server->get('HTTPS'));
        self::assertSame('https', $httpRequest->server->get('REQUEST_SCHEME'));
        self::assertEquals(443, $httpRequest->server->get('SERVER_PORT')); // Can be int or string
    }

    public function testMakeRequestWithCustomPort(): void
    {
        $psr7Request = new Psr7Request();
        $psr7Request->setMethod('GET');
        $psr7Request->setUri('http://example.com:8080/api');

        $httpRequest = $this->factory->make($psr7Request);

        self::assertEquals(8080, $httpRequest->server->get('SERVER_PORT')); // Can be int or string
    }

    public function testMakeRequestWithHeaders(): void
    {
        $psr7Request = new Psr7Request();
        $psr7Request->setMethod('GET');
        $psr7Request->setUri('http://example.com/api');
        $psr7Request->setHeaders([
            'Content-Type' => $this->createHeaderValue(['application/json']),
            'Accept' => $this->createHeaderValue(['application/json', 'text/html']),
            'Authorization' => $this->createHeaderValue(['Bearer token123']),
        ]);

        $httpRequest = $this->factory->make($psr7Request);

        self::assertSame('application/json', $httpRequest->server->get('CONTENT_TYPE'));
        self::assertSame('application/json, text/html', $httpRequest->server->get('HTTP_ACCEPT'));
        self::assertSame('Bearer token123', $httpRequest->server->get('HTTP_AUTHORIZATION'));
    }

    public function testMakeRequestWithHostHeader(): void
    {
        $psr7Request = new Psr7Request();
        $psr7Request->setMethod('GET');
        $psr7Request->setUri('http://example.com/api');
        $psr7Request->setHeaders([
            'Host' => $this->createHeaderValue(['api.example.com']),
        ]);

        $httpRequest = $this->factory->make($psr7Request);

        self::assertSame('api.example.com', $httpRequest->server->get('HTTP_HOST'));
        self::assertSame('example.com', $httpRequest->server->get('SERVER_NAME')); // URI takes precedence
    }

    public function testMakeRequestWithCookies(): void
    {
        $psr7Request = new Psr7Request();
        $psr7Request->setMethod('GET');
        $psr7Request->setUri('http://example.com/api');
        $psr7Request->setHeaders([
            'Cookie' => $this->createHeaderValue(['session=abc123; user_id=456; theme=dark']),
        ]);

        $httpRequest = $this->factory->make($psr7Request);

        self::assertSame('abc123', $httpRequest->cookies->get('session'));
        self::assertSame('456', $httpRequest->cookies->get('user_id'));
        self::assertSame('dark', $httpRequest->cookies->get('theme'));
    }

    public function testMakeRequestWithUrlEncodedCookies(): void
    {
        $psr7Request = new Psr7Request();
        $psr7Request->setMethod('GET');
        $psr7Request->setUri('http://example.com/api');
        $psr7Request->setHeaders([
            'Cookie' => $this->createHeaderValue(['name=John%20Doe; email=test%40example.com']),
        ]);

        $httpRequest = $this->factory->make($psr7Request);

        self::assertSame('John Doe', $httpRequest->cookies->get('name'));
        self::assertSame('test@example.com', $httpRequest->cookies->get('email'));
    }

    public function testMakePostRequestWithFormData(): void
    {
        $psr7Request = new Psr7Request();
        $psr7Request->setMethod('POST');
        $psr7Request->setUri('http://example.com/api/users');
        $psr7Request->setHeaders([
            'Content-Type' => $this->createHeaderValue(['application/x-www-form-urlencoded']),
        ]);
        $psr7Request->setBody('name=John&email=john@example.com&age=30');

        $httpRequest = $this->factory->make($psr7Request);

        self::assertSame('POST', $httpRequest->getMethod());
        self::assertSame('John', $httpRequest->request->get('name'));
        self::assertSame('john@example.com', $httpRequest->request->get('email'));
        self::assertSame('30', $httpRequest->request->get('age'));
    }

    public function testMakePostRequestWithJsonBody(): void
    {
        $jsonBody = '{"name":"John","email":"john@example.com"}';

        $psr7Request = new Psr7Request();
        $psr7Request->setMethod('POST');
        $psr7Request->setUri('http://example.com/api/users');
        $psr7Request->setHeaders([
            'Content-Type' => $this->createHeaderValue(['application/json']),
        ]);
        $psr7Request->setBody($jsonBody);

        $httpRequest = $this->factory->make($psr7Request);

        self::assertSame($jsonBody, $httpRequest->getContent());
        self::assertEmpty($httpRequest->request->all()); // POST array should be empty for JSON
    }

    public function testMakePostRequestWithEmptyBody(): void
    {
        $psr7Request = new Psr7Request();
        $psr7Request->setMethod('POST');
        $psr7Request->setUri('http://example.com/api/trigger');
        $psr7Request->setBody('');

        $httpRequest = $this->factory->make($psr7Request);

        self::assertSame('POST', $httpRequest->getMethod());
        self::assertSame('', $httpRequest->getContent());
        self::assertEmpty($httpRequest->request->all());
    }

    public function testMakePostRequestWithMultipartFormData(): void
    {
        $psr7Request = new Psr7Request();
        $psr7Request->setMethod('POST');
        $psr7Request->setUri('http://example.com/api/upload');
        $psr7Request->setHeaders([
            'Content-Type' => $this->createHeaderValue(['multipart/form-data; boundary=----WebKitFormBoundary']),
        ]);
        $psr7Request->setBody(
            '------WebKitFormBoundary\r\nContent-Disposition: form-data; name="field"\r\n\r\nvalue\r\n------WebKitFormBoundary--'
        );

        $httpRequest = $this->factory->make($psr7Request);

        self::assertSame('POST', $httpRequest->getMethod());
        // Multipart data is in raw body, not parsed into request
        self::assertStringContainsString('WebKitFormBoundary', $httpRequest->getContent());
    }

    public function testMakePostRequestWithXmlBody(): void
    {
        $xmlBody = '<?xml version="1.0"?><user><name>John</name></user>';

        $psr7Request = new Psr7Request();
        $psr7Request->setMethod('POST');
        $psr7Request->setUri('http://example.com/api/users');
        $psr7Request->setHeaders([
            'Content-Type' => $this->createHeaderValue(['application/xml']),
        ]);
        $psr7Request->setBody($xmlBody);

        $httpRequest = $this->factory->make($psr7Request);

        self::assertSame('POST', $httpRequest->getMethod());
        self::assertSame($xmlBody, $httpRequest->getContent());
        self::assertEmpty($httpRequest->request->all());
    }

    public function testMakePutRequestWithJsonBody(): void
    {
        $jsonBody = '{"name":"Jane Doe","email":"jane@example.com","status":"active"}';

        $psr7Request = new Psr7Request();
        $psr7Request->setMethod('PUT');
        $psr7Request->setUri('http://example.com/api/users/123');
        $psr7Request->setHeaders([
            'Content-Type' => $this->createHeaderValue(['application/json']),
        ]);
        $psr7Request->setBody($jsonBody);

        $httpRequest = $this->factory->make($psr7Request);

        self::assertSame('PUT', $httpRequest->getMethod());
        self::assertSame($jsonBody, $httpRequest->getContent());
        self::assertEmpty($httpRequest->request->all());
    }

    public function testMakePutRequestWithFormData(): void
    {
        $psr7Request = new Psr7Request();
        $psr7Request->setMethod('PUT');
        $psr7Request->setUri('http://example.com/api/users/456');
        $psr7Request->setHeaders([
            'Content-Type' => $this->createHeaderValue(['application/x-www-form-urlencoded']),
        ]);
        $psr7Request->setBody('name=Updated+Name&status=inactive');

        $httpRequest = $this->factory->make($psr7Request);

        self::assertSame('PUT', $httpRequest->getMethod());
        self::assertSame('Updated Name', $httpRequest->request->get('name'));
        self::assertSame('inactive', $httpRequest->request->get('status'));
    }

    public function testMakeDeleteRequestWithoutBody(): void
    {
        $psr7Request = new Psr7Request();
        $psr7Request->setMethod('DELETE');
        $psr7Request->setUri('http://example.com/api/users/789');

        $httpRequest = $this->factory->make($psr7Request);

        self::assertSame('DELETE', $httpRequest->getMethod());
        self::assertSame('/api/users/789', $httpRequest->getPathInfo());
        self::assertSame('', $httpRequest->getContent());
    }

    public function testMakeDeleteRequestWithQueryParameters(): void
    {
        $psr7Request = new Psr7Request();
        $psr7Request->setMethod('DELETE');
        $psr7Request->setUri('http://example.com/api/users?soft=true&reason=inactive');

        $httpRequest = $this->factory->make($psr7Request);

        self::assertSame('DELETE', $httpRequest->getMethod());
        self::assertSame('true', $httpRequest->query->get('soft'));
        self::assertSame('inactive', $httpRequest->query->get('reason'));
    }

    public function testMakeDeleteRequestWithJsonBody(): void
    {
        $jsonBody = '{"reason":"User requested account deletion","backup":true}';

        $psr7Request = new Psr7Request();
        $psr7Request->setMethod('DELETE');
        $psr7Request->setUri('http://example.com/api/users/999');
        $psr7Request->setHeaders([
            'Content-Type' => $this->createHeaderValue(['application/json']),
        ]);
        $psr7Request->setBody($jsonBody);

        $httpRequest = $this->factory->make($psr7Request);

        self::assertSame('DELETE', $httpRequest->getMethod());
        self::assertSame($jsonBody, $httpRequest->getContent());
    }

    public function testMakePatchRequestWithJsonPatch(): void
    {
        $jsonPatch = '[{"op":"replace","path":"/email","value":"newemail@example.com"}]';

        $psr7Request = new Psr7Request();
        $psr7Request->setMethod('PATCH');
        $psr7Request->setUri('http://example.com/api/users/555');
        $psr7Request->setHeaders([
            'Content-Type' => $this->createHeaderValue(['application/json-patch+json']),
        ]);
        $psr7Request->setBody($jsonPatch);

        $httpRequest = $this->factory->make($psr7Request);

        self::assertSame('PATCH', $httpRequest->getMethod());
        self::assertSame($jsonPatch, $httpRequest->getContent());
    }

    public function testMakePatchRequestWithFormData(): void
    {
        $psr7Request = new Psr7Request();
        $psr7Request->setMethod('PATCH');
        $psr7Request->setUri('http://example.com/api/users/777');
        $psr7Request->setHeaders([
            'Content-Type' => $this->createHeaderValue(['application/x-www-form-urlencoded']),
        ]);
        $psr7Request->setBody('email=updated@example.com&verified=true');

        $httpRequest = $this->factory->make($psr7Request);

        self::assertSame('PATCH', $httpRequest->getMethod());
        self::assertSame('updated@example.com', $httpRequest->request->get('email'));
        self::assertSame('true', $httpRequest->request->get('verified'));
    }

    public function testMakePostRequestWithSpecialCharactersInBody(): void
    {
        $psr7Request = new Psr7Request();
        $psr7Request->setMethod('POST');
        $psr7Request->setUri('http://example.com/api/data');
        $psr7Request->setHeaders([
            'Content-Type' => $this->createHeaderValue(['application/x-www-form-urlencoded']),
        ]);
        $psr7Request->setBody('text=Hello+World%21&special=%26%3D%3F');

        $httpRequest = $this->factory->make($psr7Request);

        self::assertSame('POST', $httpRequest->getMethod());
        self::assertSame('Hello World!', $httpRequest->request->get('text'));
        self::assertSame('&=?', $httpRequest->request->get('special'));
    }

    public function testMakePostRequestWithAuthorizationHeader(): void
    {
        $psr7Request = new Psr7Request();
        $psr7Request->setMethod('POST');
        $psr7Request->setUri('http://example.com/api/secure/data');
        $psr7Request->setHeaders([
            'Authorization' => $this->createHeaderValue(['Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9']),
            'Content-Type' => $this->createHeaderValue(['application/json']),
        ]);
        $psr7Request->setBody('{"data":"sensitive"}');

        $httpRequest = $this->factory->make($psr7Request);

        self::assertSame('POST', $httpRequest->getMethod());
        self::assertSame(
            'Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9',
            $httpRequest->server->get('HTTP_AUTHORIZATION')
        );
    }

    public function testMakeRequestWithPath(): void
    {
        $psr7Request = new Psr7Request();
        $psr7Request->setMethod('GET');
        $psr7Request->setUri('http://example.com/api/users/123');

        $httpRequest = $this->factory->make($psr7Request);

        self::assertSame('/api/users/123', $httpRequest->getPathInfo());
    }

    public function testMakeRequestWithRemoteAddr(): void
    {
        $psr7Request = new Psr7Request();
        $psr7Request->setMethod('GET');
        $psr7Request->setUri('http://example.com/api');

        $httpRequest = $this->factory->make($psr7Request);

        self::assertSame('127.0.0.1', $httpRequest->server->get('REMOTE_ADDR'));
        self::assertSame('0', $httpRequest->server->get('REMOTE_PORT'));
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

        self::assertTrue(isset($headers['Content-Type']));
        self::assertTrue(isset($headers['X-Custom-Header']));
        self::assertTrue(isset($headers['Cache-Control']));

        // Verify header values
        $contentType = $headers['Content-Type'];
        self::assertInstanceOf(HeaderValue::class, $contentType);
        self::assertContains('application/json', iterator_to_array($contentType->getValue()));
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

    public function testMakePutRequest(): void
    {
        $psr7Request = new Psr7Request();
        $psr7Request->setMethod('PUT');
        $psr7Request->setUri('http://example.com/api/users/123');
        $psr7Request->setHeaders([
            'Content-Type' => $this->createHeaderValue(['application/x-www-form-urlencoded']),
        ]);
        $psr7Request->setBody('name=Jane&email=jane@example.com');

        $httpRequest = $this->factory->make($psr7Request);

        self::assertSame('PUT', $httpRequest->getMethod());
        self::assertSame('Jane', $httpRequest->request->get('name'));
        self::assertSame('jane@example.com', $httpRequest->request->get('email'));
    }

    public function testMakeDeleteRequest(): void
    {
        $psr7Request = new Psr7Request();
        $psr7Request->setMethod('DELETE');
        $psr7Request->setUri('http://example.com/api/users/123');

        $httpRequest = $this->factory->make($psr7Request);

        self::assertSame('DELETE', $httpRequest->getMethod());
        self::assertSame('http://example.com/api/users/123', $httpRequest->getUri());
    }

    public function testMakeRequestWithEmptyUri(): void
    {
        $psr7Request = new Psr7Request();
        $psr7Request->setMethod('GET');
        $psr7Request->setUri('');

        $httpRequest = $this->factory->make($psr7Request);

        self::assertSame('localhost', $httpRequest->server->get('SERVER_NAME'));
        self::assertSame('http', $httpRequest->server->get('REQUEST_SCHEME'));
    }

    public function testMakeRequestWithContentLength(): void
    {
        $body = 'test content';
        $psr7Request = new Psr7Request();
        $psr7Request->setMethod('POST');
        $psr7Request->setUri('http://example.com/api');
        $psr7Request->setHeaders([
            'Content-Length' => $this->createHeaderValue(['12']),
        ]);
        $psr7Request->setBody($body);

        $httpRequest = $this->factory->make($psr7Request);

        self::assertSame('12', $httpRequest->server->get('CONTENT_LENGTH'));
    }

    /**
     * Helper method to create a HeaderValue instance.
     *
     * @param array<string> $values
     */
    private function createHeaderValue(array $values): HeaderValue
    {
        $headerValue = new HeaderValue();
        $headerValue->setValue($values);

        return $headerValue;
    }
}

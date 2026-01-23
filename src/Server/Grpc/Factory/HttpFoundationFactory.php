<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Grpc\Factory;

use SwooleBundle\SwooleBundle\Server\Grpc\Generated\HeaderValue;
use SwooleBundle\SwooleBundle\Server\Grpc\Generated\Psr7Request;
use SwooleBundle\SwooleBundle\Server\Grpc\Generated\Psr7Response;
use Symfony\Component\HttpFoundation\Request as HttpFoundationRequest;
use Symfony\Component\HttpFoundation\Response as HttpFoundationResponse;

final class HttpFoundationFactory
{
    /**
     * Convert a PSR-7 Request protobuf message to a Symfony HttpFoundation Request.
     * In testing, not production ready.
     */
    public function make(Psr7Request $request): HttpFoundationRequest
    {
        $method = $request->getMethod();
        $body = $request->getBody();

        // Build server array for additional server variables not set by create()
        $server = [
            'SERVER_PROTOCOL' => $request->getProtocolVersion() ?: 'HTTP/1.1',
            'REMOTE_ADDR' => '127.0.0.1', // Default for gRPC requests
            'REMOTE_PORT' => '0',
        ];

        $cookies = [];
        $hostHeader = null;

        // Convert headers from Psr7Request format to server variables
        foreach ($request->getHeaders() as $name => $headerValue) {
            /** @var HeaderValue $headerValue */
            $values = $headerValue->getValue();
            $valueArray = [];
            foreach ($values as $value) {
                $valueArray[] = $value;
            }

            $lowerName = strtolower($name);
            if ($lowerName === 'content-type') {
                $server['CONTENT_TYPE'] = implode(', ', $valueArray);
            } elseif ($lowerName === 'content-length') {
                $server['CONTENT_LENGTH'] = implode(', ', $valueArray);
            } elseif ($lowerName === 'host') {
                // Store Host header to override after request creation
                $hostHeader = implode(', ', $valueArray);
            } elseif ($lowerName === 'cookie') {
                $cookies = $this->parseCookies(implode('; ', $valueArray));
            } else {
                $server['HTTP_' . strtoupper(str_replace('-', '_', $name))] = implode(', ', $valueArray);
            }
        }

        // Parse POST data from body for form-encoded requests
        $parameters = [];
        if (in_array(strtoupper($method), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $contentType = $server['CONTENT_TYPE'] ?? '';
            if (str_contains($contentType, 'application/x-www-form-urlencoded')) {
                parse_str($body, $parameters);
            }
        }

        // HttpFoundationRequest::create() automatically handles:
        // - URI parsing (scheme, host, port, path, query)
        // - HTTPS detection from scheme
        // - SERVER_PORT, REQUEST_SCHEME, PATH_INFO from URI
        // - Query parameter extraction
        $httpRequest = HttpFoundationRequest::create(
            $request->getUri(),
            $method,
            $parameters,
            $cookies,
            [],
            $server,
            $body
        );

        // Set REQUEST_SCHEME from the scheme detected by create()
        $httpRequest->server->set('REQUEST_SCHEME', $httpRequest->getScheme());

        // Override HTTP_HOST if a Host header was provided
        if ($hostHeader !== null) {
            $httpRequest->headers->set('Host', $hostHeader);
            $httpRequest->server->set('HTTP_HOST', $hostHeader);
        }

        return $httpRequest;
    }

    /**
     * Parse cookies from a Cookie header string.
     *
     * @return array<string, string>
     */
    private function parseCookies(string $cookieHeader): array
    {
        $cookies = [];

        if ($cookieHeader === '') {
            return $cookies;
        }

        $pairs = explode('; ', $cookieHeader);
        foreach ($pairs as $pair) {
            $parts = explode('=', $pair, 2);
            if (count($parts) !== 2) {
                continue;
            }

            [$name, $value] = $parts;
            $cookies[trim($name)] = urldecode(trim($value));
        }

        return $cookies;
    }

    /**
     * Convert a Symfony HttpFoundation Response to a PSR-7 Response protobuf message.
     */
    public function convertResponse(HttpFoundationResponse $response): Psr7Response
    {
        $psr7Response = new Psr7Response();

        // Set status code
        $psr7Response->setStatusCode($response->getStatusCode());

        // Set protocol version
        $psr7Response->setProtocolVersion($response->getProtocolVersion());

        // Set reason phrase - HttpFoundation doesn't expose this directly
        // Use empty string as default to avoid issues with placeholder values like "-"
        $reasonPhrase = $response->headers->get('reason-phrase', '');
        if ($reasonPhrase === '-' || $reasonPhrase === null) {
            $reasonPhrase = '';
        }
        $psr7Response->setReasonPhrase($reasonPhrase);

        // Convert headers (exclude cookies as they're in separate handling)
        $headers = [];
        foreach ($response->headers->allPreserveCaseWithoutCookies() as $name => $values) {
            $headerValue = new HeaderValue();
            $headerValue->setValue($values);
            $headers[$name] = $headerValue;
        }

        // Add cookies as Set-Cookie headers
        foreach ($response->headers->getCookies() as $cookie) {
            $cookieHeader = $cookie->__toString();
            if (!isset($headers['Set-Cookie'])) {
                $headers['Set-Cookie'] = new HeaderValue();
                $headers['Set-Cookie']->setValue([$cookieHeader]);
            } else {
                $currentValues = iterator_to_array($headers['Set-Cookie']->getValue());
                $currentValues[] = $cookieHeader;
                $headers['Set-Cookie']->setValue($currentValues);
            }
        }

        $psr7Response->setHeaders($headers);

        // Set body content
        $content = $response->getContent();
        $psr7Response->setBody($content !== false ? $content : '');

        return $psr7Response;
    }
}

<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Client;

use Swoole\Coroutine;
use Swoole\Coroutine\Http\Client;
use SwooleBundle\SwooleBundle\Client\Exception\ClientConnectionErrorException;
use SwooleBundle\SwooleBundle\Client\Exception\MissingContentTypeException;
use SwooleBundle\SwooleBundle\Client\Exception\UnsupportedContentTypeException;
use SwooleBundle\SwooleBundle\Client\Exception\UnsupportedHttpMethodException;
use SwooleBundle\SwooleBundle\Server\Config\Socket;

/**
 * Mainly used for server tests.
 *
 * @internal Class API is not stable, nor it is guaranteed to exists in next releases, use at own risk
 */
final class HttpClient
{
    private const SUPPORTED_HTTP_METHODS = [
        Http::METHOD_GET,
        Http::METHOD_HEAD,
        Http::METHOD_POST,
        Http::METHOD_DELETE,
        Http::METHOD_PATCH,
        Http::METHOD_TRACE,
        Http::METHOD_OPTIONS,
    ];

    private const SUPPORTED_CONTENT_TYPES = [
        Http::CONTENT_TYPE_APPLICATION_JSON,
        Http::CONTENT_TYPE_TEXT_PLAIN,
        Http::CONTENT_TYPE_TEXT_HTML,
    ];

    private const ACCEPTABLE_CONNECTING_EXIT_CODES = [
        111 => true,
        61 => true,
        60 => true,
    ];

    public function __construct(private Client $client)
    {
    }

    public function __serialize(): array
    {
        return [
            'host' => $this->client->host,
            'port' => $this->client->port,
            'ssl' => $this->client->ssl,
            'options' => $this->client->setting,
        ];
    }

    public function __unserialize(array $spec): void
    {
        $this->client = self::makeSwooleClient($spec['host'], $spec['port'], $spec['ssl'], $spec['options']);
    }

    public static function fromSocket(Socket $socket, array $options = []): self
    {
        return self::fromDomain(
            $socket->host(),
            $socket->port(),
            $socket->ssl(),
            $options
        );
    }

    public static function fromDomain(string $host, int $port = 443, bool $ssl = true, array $options = []): self
    {
        return new self(self::makeSwooleClient($host, $port, $ssl, $options));
    }

    /**
     * @param int $timeout seconds
     * @param int $step    microseconds
     *
     * @return bool Success
     */
    public function connect(int $timeout = 3, int $step = 1, bool $waitIfNoConnection = false): bool
    {
        $start = \microtime(true);
        $max = $start + $timeout;

        do {
            try {
                $this->send('/', Http::METHOD_HEAD);

                return true;
            } catch (\RuntimeException $ex) {
                $throw = true;

                if ($waitIfNoConnection && 5001 === $ex->getCode()) { // Connection Failed
                    $throw = false;
                }

                if ($throw && !isset(self::ACCEPTABLE_CONNECTING_EXIT_CODES[$ex->getCode()])) {
                    throw $ex;
                }
            }

            Coroutine::sleep($step);
            $now = \microtime(true);
        } while ($now < $max);

        return false;
    }

    /**
     * @param null|mixed $data
     *
     * @return array<string, array<string, mixed>>
     */
    public function send(string $path, string $method = Http::METHOD_GET, array $headers = [], $data = null, int $timeout = 3): array
    {
        $this->assertHttpMethodSupported($method);

        $this->client->setMethod($method);
        $this->client->setHeaders($headers);

        if (null !== $data) {
            if (\is_string($data)) {
                $this->client->setData($data);
            } else {
                $this->serializeRequestData($this->client, $data);
            }
        }

        $this->client->execute($path);

        return $this->resolveResponse($this->client, $timeout);
    }

    private static function makeSwooleClient(string $host, int $port = 443, bool $ssl = true, array $options = []): Client
    {
        $client = new Client(
            $host,
            $port,
            $ssl
        );

        if (!empty($options)) {
            $client->set($options);
        }

        return $client;
    }

    private function assertHttpMethodSupported(string $method): void
    {
        if (true === \in_array($method, self::SUPPORTED_HTTP_METHODS, true)) {
            return;
        }

        throw UnsupportedHttpMethodException::forMethod($method, self::SUPPORTED_HTTP_METHODS);
    }

    private function serializeRequestData(Client $client, mixed $data): void
    {
        $json = \json_encode($data, \JSON_THROW_ON_ERROR);
        $client->requestHeaders[Http::HEADER_CONTENT_TYPE] = Http::CONTENT_TYPE_APPLICATION_JSON;
        $client->setData($json);
    }

    private function resolveResponse(Client $client, int $timeout): array
    {
        $client->recv($timeout);
        $this->assertConnectionSuccessful($client);

        return [
            'request' => [
                'method' => $client->requestMethod,
                'headers' => $client->requestHeaders,
                'body' => $client->requestBody,
                'cookies' => $client->set_cookie_headers,
                'uploadFiles' => $client->uploadFiles,
            ],
            'response' => [
                'cookies' => $client->cookies,
                'headers' => $client->headers,
                'statusCode' => $client->statusCode,
                'body' => $this->resolveResponseBody($client),
                'downloadFile' => $client->downloadFile,
                'downloadOffset' => $client->downloadOffset,
            ],
        ];
    }

    private function assertConnectionSuccessful(Client $client): void
    {
        if ($client->statusCode >= 0) {
            return;
        }

        switch ($client->statusCode) {
            case -1:
                throw ClientConnectionErrorException::failed($client->errCode);
            case -2:
                throw ClientConnectionErrorException::requestTimeout($client->errCode);
            case -3:
                throw ClientConnectionErrorException::serverReset($client->errCode);
            default:
                throw ClientConnectionErrorException::unknown($client->errCode);
        }
    }

    private function resolveResponseBody(Client $client): array|string
    {
        if (204 === $client->statusCode || '' === $client->body) {
            return [];
        }

        $this->assertHasContentType($client);
        $fullContentType = $client->headers[Http::HEADER_CONTENT_TYPE];
        $contentType = \explode(';', (string) $fullContentType)[0];

        return match ($contentType) {
            Http::CONTENT_TYPE_APPLICATION_JSON => \json_decode((string) $client->body, true, 512, \JSON_THROW_ON_ERROR),
            Http::CONTENT_TYPE_TEXT_PLAIN, Http::CONTENT_TYPE_TEXT_HTML => $client->body,
            default => throw UnsupportedContentTypeException::forContentType($contentType, self::SUPPORTED_CONTENT_TYPES),
        };
    }

    private function assertHasContentType(Client $client): void
    {
        if (true === \array_key_exists(Http::HEADER_CONTENT_TYPE, $client->headers)) {
            return;
        }

        throw MissingContentTypeException::make();
    }
}

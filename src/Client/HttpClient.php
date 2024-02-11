<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Client;

use RuntimeException;
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
 * @phpstan-type SerializedClient = array{
 *   host: string,
 *   port: int,
 *   ssl: bool,
 *   options: array<string, mixed>,
 * }
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
        60 => true,
        61 => true,
        111 => true,
    ];

    public function __construct(private Client $client) {}

    /**
     * @return SerializedClient
     */
    public function __serialize(): array
    {
        return [
            'host' => $this->client->host,
            'port' => $this->client->port,
            'ssl' => $this->client->ssl,
            'options' => $this->client->setting,
        ];
    }

    /**
     * @param SerializedClient $spec
     */
    public function __unserialize(array $spec): void
    {
        $this->client = self::makeSwooleClient($spec['host'], $spec['port'], $spec['ssl'], $spec['options']);
    }

    /**
     * @param array<string, mixed> $options
     */
    public static function fromSocket(Socket $socket, array $options = []): self
    {
        return self::fromDomain(
            $socket->host(),
            $socket->port(),
            $socket->ssl(),
            $options
        );
    }

    /**
     * @param array<string, mixed> $options
     */
    public static function fromDomain(string $host, int $port = 443, bool $ssl = true, array $options = []): self
    {
        return new self(self::makeSwooleClient($host, $port, $ssl, $options));
    }

    /**
     * @param int $timeout seconds
     * @param int $step microseconds
     * @return bool Success
     */
    public function connect(int $timeout = 3, int $step = 1, bool $waitIfNoConnection = false): bool
    {
        $start = microtime(true);
        $max = $start + $timeout;

        do {
            try {
                $this->send('/', Http::METHOD_HEAD);

                return true;
            } catch (RuntimeException $ex) {
                $throw = true;

                if ($waitIfNoConnection && $ex->getCode() === 5001) { // Connection Failed
                    $throw = false;
                }

                if ($throw && !isset(self::ACCEPTABLE_CONNECTING_EXIT_CODES[$ex->getCode()])) {
                    throw $ex;
                }
            }

            Coroutine::sleep($step);
            $now = microtime(true);
        } while ($now < $max);

        return false;
    }

    /**
     * @param array<string, string> $headers
     * @return array<string, array<string, mixed>>
     */
    public function send(
        string $path,
        Http $method = Http::METHOD_GET,
        array $headers = [],
        mixed $data = null,
        int $timeout = 3,
    ): array {
        $this->assertHttpMethodSupported($method);

        $this->client->setMethod($method->value);
        $this->client->setHeaders($headers);

        if ($data !== null) {
            if (is_string($data)) {
                $this->client->setData($data);
            } else {
                $this->serializeRequestData($this->client, $data);
            }
        }

        $this->client->execute($path);

        return $this->resolveResponse($this->client, $timeout);
    }

    /**
     * @param array<string, mixed> $options
     */
    private static function makeSwooleClient(
        string $host,
        int $port = 443,
        bool $ssl = true,
        array $options = [],
    ): Client {
        $client = new Client($host, $port, $ssl);

        if (!empty($options)) {
            $client->set($options);
        }

        return $client;
    }

    private function assertHttpMethodSupported(Http $method): void
    {
        if (in_array($method, self::SUPPORTED_HTTP_METHODS, true) === true) {
            return;
        }

        throw UnsupportedHttpMethodException::forMethod(
            $method->value,
            array_map(static fn(Http $http): string => $http->value, self::SUPPORTED_HTTP_METHODS),
        );
    }

    private function serializeRequestData(Client $client, mixed $data): void
    {
        $json = json_encode($data, JSON_THROW_ON_ERROR);
        $client->requestHeaders[Http::HEADER_CONTENT_TYPE->value] = Http::CONTENT_TYPE_APPLICATION_JSON;
        $client->setData($json);
    }

    /**
     * @return array{
     *   request: array{
     *     method: string,
     *     headers: array<string, string>,
     *     body: string,
     *     cookies: array<string, string>,
     *     uploadFiles: array<array<string, string>>,
     *   },
     *   response: array{
     *     cookies: array<string, string>,
     *     headers: array<string, string>,
     *     statusCode: int,
     *     body: array|string,
     *     downloadFile: string,
     *     downloadOffset: int,
     *   },
     * }
     */
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
        if ($client->statusCode === 204 || $client->body === '') {
            return [];
        }

        $this->assertHasContentType($client);
        $fullContentType = $client->headers[Http::HEADER_CONTENT_TYPE->value];
        $contentType = explode(';', (string) $fullContentType)[0];

        return match ($contentType) {
            Http::CONTENT_TYPE_APPLICATION_JSON->value => json_decode(
                (string) $client->body,
                true,
                512,
                JSON_THROW_ON_ERROR
            ),
            Http::CONTENT_TYPE_TEXT_PLAIN->value,
            Http::CONTENT_TYPE_TEXT_HTML->value => $client->body,
            default => throw UnsupportedContentTypeException::forContentType(
                $contentType,
                array_map(static fn(Http $http) => $http->value, self::SUPPORTED_CONTENT_TYPES),
            ),
        };
    }

    private function assertHasContentType(Client $client): void
    {
        if (array_key_exists(Http::HEADER_CONTENT_TYPE->value, $client->headers) === true) {
            return;
        }

        throw MissingContentTypeException::make();
    }
}

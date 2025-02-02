<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Api;

use SwooleBundle\SwooleBundle\Client\Http;
use SwooleBundle\SwooleBundle\Client\HttpClient;

/**
 * @phpstan-import-type MetricsShape from Api
 * @phpstan-import-type ServerStatusShape from Api
 */
final readonly class ApiServerClient implements Api
{
    public function __construct(private HttpClient $client) {}

    /**
     * Get Swoole HTTP Server status.
     *
     * {@inheritDoc}
     */
    public function status(): array
    {
        /** @var ServerStatusShape $toReturn */
        $toReturn = $this->client->send('/api/server')['response']['body'];

        return $toReturn;
    }

    /**
     * Shutdown Swoole HTTP Server.
     */
    public function shutdown(): void
    {
        $this->client->send('/api/server', Http::METHOD_DELETE);
    }

    /**
     * Reload Swoole HTTP Server workers.
     */
    public function reload(): void
    {
        $this->client->send('/api/server', Http::METHOD_PATCH);
    }

    /**
     * Get Swoole HTTP Server metrics.
     *
     * {@inheritDoc}
     */
    public function metrics(): array
    {
        /** @var array{date: string, server: MetricsShape} $toReturn */
        $toReturn = $this->client->send('/api/server/metrics')['response']['body'];

        return $toReturn;
    }
}

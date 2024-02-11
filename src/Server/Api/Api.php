<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Api;

use SwooleBundle\SwooleBundle\Metrics\MetricsProvider;

/**
 * Swoole HTTP Server API.
 *
 * @phpstan-import-type MetricsShape from MetricsProvider
 * @phpstan-type ServerStatusShape = array{
 *   date: string,
 *   server: array{
 *     host: string,
 *     listeners: array<array{host: string, port: int}>,
 *     port: int,
 *     processes: array{
 *       manager: array{pid: int},
 *       master: array{pid: int},
 *       worker: array{id: int, pid: int},
 *     },
 *     runningMode: string,
 *     settings: array<string, mixed>,
 *   }
 * }
 */
interface Api
{
    /**
     * Get Swoole HTTP Server status.
     *
     * @return ServerStatusShape
     */
    public function status(): array;

    /**
     * Shutdown Swoole HTTP Server.
     */
    public function shutdown(): void;

    /**
     * Reload Swoole HTTP Server workers.
     */
    public function reload(): void;

    /**
     * Get Swoole HTTP Server metrics.
     *
     * @return array{
     *   date: string,
     *   server: MetricsShape
     * }
     */
    public function metrics(): array;
}

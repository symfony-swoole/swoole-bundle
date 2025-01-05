<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Api;

use DateTimeImmutable;
use Swoole\Server\Port;
use SwooleBundle\SwooleBundle\Server\HttpServer;
use SwooleBundle\SwooleBundle\Server\HttpServerConfiguration;

/**
 * API Server for Swoole HTTP Server. If enabled, is running on another port, than regular server.
 * Used to control original Swoole HTTP Server.
 */
final class ApiServer implements Api
{
    public function __construct(
        private readonly HttpServer $server,
        private readonly HttpServerConfiguration $serverConfiguration,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function metrics(): array
    {
        $metrics = $this->server->metrics();

        return [
            'date' => (new DateTimeImmutable('now'))->format(DATE_ATOM),
            'server' => $metrics,
        ];
    }

    public function shutdown(): void
    {
        $this->server->shutdown();
    }

    public function reload(): void
    {
        $this->server->reload();
    }

    /**
     * {@inheritDoc}
     */
    public function status(): array
    {
        $swooleServer = $this->server->getServer();

        return [
            'date' => date(DATE_ATOM),
            'server' => [
                'host' => $swooleServer->host,
                'listeners' => $this->extractListenersStatus($this->server),
                'port' => $swooleServer->port,
                'processes' => $this->extractProcessesStatus($this->server),
                'runningMode' => $this->serverConfiguration->getRunningMode(),
                'settings' => $swooleServer->setting,
            ],
        ];
    }

    /**
     * @return array<array{host: string, port: int}>
     */
    private function extractListenersStatus(HttpServer $server): array
    {
        return array_values(array_map(static fn(Port $listener): array => [
            'host' => $listener->host,
            'port' => $listener->port,
        ], $server->getListeners()));
    }

    /**
     * @return array{
     *   manager: array{pid: int},
     *   master: array{pid: int},
     *   worker: array{id: int, pid: int}
     * }
     */
    private function extractProcessesStatus(HttpServer $server): array
    {
        $swooleServer = $server->getServer();

        return [
            'manager' => [
                'pid' => $swooleServer->manager_pid,
            ],
            'master' => [
                'pid' => $swooleServer->master_pid,
            ],
            'worker' => [
                'id' => $swooleServer->worker_id,
                'pid' => $swooleServer->worker_pid,
            ],
        ];
    }
}

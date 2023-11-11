<?php

declare(strict_types=1);

namespace K911\Swoole\Server\Api;

use K911\Swoole\Server\HttpServer;
use K911\Swoole\Server\HttpServerConfiguration;
use Swoole\Server\Port;

/**
 * API Server for Swoole HTTP Server. If enabled, is running on another port, than regular server.
 * Used to control original Swoole HTTP Server.
 */
final class ApiServer implements ApiServerInterface
{
    public function __construct(
        private HttpServer $server,
        private HttpServerConfiguration $serverConfiguration
    ) {
    }

    public function metrics(): array
    {
        $metrics = $this->server->metrics();

        return [
            'date' => (new \DateTimeImmutable('now'))->format(\DATE_ATOM),
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

    public function status(): array
    {
        $swooleServer = $this->server->getServer();

        return [
            'date' => date(\DATE_ATOM),
            'server' => [
                'host' => $swooleServer->host,
                'port' => $swooleServer->port,
                'runningMode' => $this->serverConfiguration->getRunningMode(),
                'processes' => $this->extractProcessesStatus($this->server),
                'settings' => $swooleServer->setting,
                'listeners' => $this->extractListenersStatus($this->server),
            ],
        ];
    }

    private function extractListenersStatus(HttpServer $server): array
    {
        return array_values(array_map(function (Port $listener): array {
            return [
                'host' => property_exists($listener, 'host') ? $listener->host : '-',
                'port' => $listener->port,
            ];
        }, $server->getListeners()));
    }

    private function extractProcessesStatus(HttpServer $server): array
    {
        $swooleServer = $server->getServer();

        return [
            'master' => [
                'pid' => $swooleServer->master_pid,
            ],
            'manager' => [
                'pid' => $swooleServer->manager_pid,
            ],
            'worker' => [
                'id' => $swooleServer->worker_id,
                'pid' => $swooleServer->worker_pid,
            ],
        ];
    }
}

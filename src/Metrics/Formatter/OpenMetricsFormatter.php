<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Metrics\Formatter;

use SwooleBundle\SwooleBundle\Metrics\MetricsProvider;

/**
 * Formats server metrics using the OpenMetrics text exposition format,
 * consumable by Prometheus-compatible scrapers.
 *
 * @see https://github.com/prometheus/OpenMetrics/blob/main/specification/OpenMetrics.md
 * @phpstan-import-type MetricsShape from MetricsProvider
 */
final class OpenMetricsFormatter implements MetricsFormatter
{
    private const string CONTENT_TYPE = 'application/openmetrics-text; version=1.0.0; charset=utf-8';

    private const string MEDIA_TYPE = 'application/openmetrics-text';

    public function __construct(
        private readonly MetricsProvider $metricsProvider,
    ) {}

    public function supports(string $accept): bool
    {
        return str_contains($accept, self::MEDIA_TYPE);
    }

    public function contentType(): string
    {
        return self::CONTENT_TYPE;
    }

    /**
     * @param MetricsShape $metricsData
     */
    public function format(array $metricsData): string
    {
        $metrics = $this->metricsProvider->fromMetricsData($metricsData);

        $lines = [
            ...self::gauge(
                'swoole_uptime_seconds',
                'Time in seconds since the server start.',
                $metrics->upTimeInSeconds(),
            ),
            ...self::counter(
                'swoole_requests',
                'Number of requests handled by the server.',
                $metrics->requestCount(),
            ),
            ...self::gauge(
                'swoole_connections_active',
                'Number of currently open connections.',
                $metrics->activeConnections(),
            ),
            ...self::counter(
                'swoole_connections_accepted',
                'Number of connections accepted by the server.',
                $metrics->acceptedConnections(),
            ),
            ...self::counter(
                'swoole_connections_closed',
                'Number of connections closed by the server.',
                $metrics->closedConnections(),
            ),
            ...self::gauge(
                'swoole_workers_total',
                'Number of worker processes.',
                $metrics->totalWorkers(),
            ),
            ...self::gauge(
                'swoole_workers_active',
                'Number of worker processes currently handling requests.',
                $metrics->activeWorkers(),
            ),
            ...self::gauge(
                'swoole_workers_idle',
                'Number of idle worker processes.',
                $metrics->idleWorkers(),
            ),
            ...self::gauge(
                'swoole_coroutines_running',
                'Number of currently running coroutines.',
                $metrics->runningCoroutines(),
            ),
            ...self::gauge(
                'swoole_tasks_queued',
                'Number of tasks waiting in the task queue.',
                $metrics->tasksInQueue(),
            ),
            ...self::floatGauge(
                'swoole_event_loop_lag_milliseconds',
                'Current event loop lag in milliseconds.',
                $metrics->eventLoopLagMs(),
            ),
            ...self::floatGauge(
                'swoole_event_loop_lag_max_milliseconds',
                'Maximum event loop lag in milliseconds since the server start.',
                $metrics->maxEventLoopLagMs(),
            ),
            ...self::floatGauge(
                'swoole_event_loop_lag_avg_milliseconds',
                'Average event loop lag in milliseconds.',
                $metrics->avgEventLoopLagMs(),
            ),
        ];

        return implode('', $lines) . "# EOF\n";
    }

    /**
     * @return array<string>
     */
    private static function gauge(string $name, string $help, int $value): array
    {
        return [
            sprintf("# TYPE %s gauge\n", $name),
            sprintf("# HELP %s %s\n", $name, $help),
            sprintf("%s %d\n", $name, $value),
        ];
    }

    /**
     * @return array<string>
     */
    private static function counter(string $name, string $help, int $value): array
    {
        return [
            sprintf("# TYPE %s counter\n", $name),
            sprintf("# HELP %s %s\n", $name, $help),
            sprintf("%s_total %d\n", $name, $value),
        ];
    }

    /**
     * @return array<string>
     */
    private static function floatGauge(string $name, string $help, ?float $value): array
    {
        if ($value === null) {
            return [];
        }

        return [
            sprintf("# TYPE %s gauge\n", $name),
            sprintf("# HELP %s %s\n", $name, $help),
            sprintf("%s %s\n", $name, self::formatFloat($value)),
        ];
    }

    private static function formatFloat(float $value): string
    {
        $formatted = rtrim(rtrim(number_format($value, 9, '.', ''), '0'), '.');

        return $formatted === '' ? '0' : $formatted;
    }
}

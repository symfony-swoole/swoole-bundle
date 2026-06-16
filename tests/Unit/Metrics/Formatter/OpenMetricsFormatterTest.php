<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Metrics\Formatter;

use PHPUnit\Framework\TestCase;
use SwooleBundle\SwooleBundle\Metrics\Formatter\OpenMetricsFormatter;
use SwooleBundle\SwooleBundle\Metrics\Metrics;
use SwooleBundle\SwooleBundle\Metrics\MetricsProvider;

final class OpenMetricsFormatterTest extends TestCase
{
    private const array RAW_METRICS = [
        'date' => '2020-01-01T00:00:00+00:00',
        'server' => [
            'start_time' => 0,
            'worker_num' => 106,
            'idle_worker_num' => 108,
            'request_count' => 101,
            'connection_num' => 103,
            'accept_count' => 104,
            'abort_count' => 105,
            'coroutine_num' => 109,
            'tasking_num' => 110,
        ],
    ];

    public function testContentTypeIsOpenMetrics(): void
    {
        $formatter = new OpenMetricsFormatter($this->fixedMetricsProvider());

        self::assertSame('application/openmetrics-text; version=1.0.0; charset=utf-8', $formatter->contentType());
    }

    public function testSupportsOpenMetricsAcceptHeader(): void
    {
        $formatter = new OpenMetricsFormatter($this->fixedMetricsProvider());

        self::assertTrue($formatter->supports('application/openmetrics-text; version=1.0.0'));
        self::assertTrue($formatter->supports('text/plain, application/openmetrics-text, */*'));
        self::assertFalse($formatter->supports('application/json'));
        self::assertFalse($formatter->supports('*/*'));
        self::assertFalse($formatter->supports(''));
    }

    public function testFormatsMetricsAsOpenMetricsText(): void
    {
        $formatter = new OpenMetricsFormatter($this->fixedMetricsProvider());

        $expected = <<<'OPENMETRICS'
            # TYPE swoole_uptime_seconds gauge
            # HELP swoole_uptime_seconds Time in seconds since the server start.
            swoole_uptime_seconds 102
            # TYPE swoole_requests counter
            # HELP swoole_requests Number of requests handled by the server.
            swoole_requests_total 101
            # TYPE swoole_connections_active gauge
            # HELP swoole_connections_active Number of currently open connections.
            swoole_connections_active 103
            # TYPE swoole_connections_accepted counter
            # HELP swoole_connections_accepted Number of connections accepted by the server.
            swoole_connections_accepted_total 104
            # TYPE swoole_connections_closed counter
            # HELP swoole_connections_closed Number of connections closed by the server.
            swoole_connections_closed_total 105
            # TYPE swoole_workers_total gauge
            # HELP swoole_workers_total Number of worker processes.
            swoole_workers_total 106
            # TYPE swoole_workers_active gauge
            # HELP swoole_workers_active Number of worker processes currently handling requests.
            swoole_workers_active 107
            # TYPE swoole_workers_idle gauge
            # HELP swoole_workers_idle Number of idle worker processes.
            swoole_workers_idle 108
            # TYPE swoole_coroutines_running gauge
            # HELP swoole_coroutines_running Number of currently running coroutines.
            swoole_coroutines_running 109
            # TYPE swoole_tasks_queued gauge
            # HELP swoole_tasks_queued Number of tasks waiting in the task queue.
            swoole_tasks_queued 110
            # EOF

            OPENMETRICS;

        self::assertSame($expected, $formatter->format(self::RAW_METRICS));
    }

    private function fixedMetricsProvider(): MetricsProvider
    {
        return new class implements MetricsProvider {
            /**
             * @param array<mixed> $metricsData
             */
            public function fromMetricsData(array $metricsData): Metrics
            {
                return new Metrics(
                    requestCount: 101,
                    upTimeInSeconds: 102,
                    activeConnections: 103,
                    acceptedConnections: 104,
                    closedConnections: 105,
                    totalWorkers: 106,
                    activeWorkers: 107,
                    idleWorkers: 108,
                    runningCoroutines: 109,
                    tasksInQueue: 110,
                );
            }
        };
    }
}

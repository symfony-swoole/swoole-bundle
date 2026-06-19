<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\OpenSwoole\Metrics;

use PHPUnit\Framework\TestCase;
use SwooleBundle\SwooleBundle\Bridge\OpenSwoole\Metrics\MetricsProvider;

final class MetricsProviderTest extends TestCase
{
    private const array SERVER_METRICS = [
        'start_time' => 0,
        'workers_total' => 4,
        'workers_idle' => 1,
        'requests_total' => 101,
        'connections_active' => 103,
        'connections_accepted' => 104,
        'connections_closed' => 105,
        'coroutine_num' => 109,
        'tasking_num' => 110,
    ];

    public function testReadsEventLoopLagInMilliseconds(): void
    {
        $provider = new MetricsProvider();

        $metrics = $provider->fromMetricsData([
            'date' => '2020-01-01T00:00:00+00:00',
            'server' => [
                ...self::SERVER_METRICS,
                'event_loop_lag_ms' => 2.5,
                'event_loop_lag_max_ms' => 120.0,
                'event_loop_lag_avg_ms' => 4.0,
            ],
        ]);

        self::assertSame(2.5, $metrics->eventLoopLagMs());
        self::assertSame(120.0, $metrics->maxEventLoopLagMs());
        self::assertSame(4.0, $metrics->avgEventLoopLagMs());
    }

    public function testPreservesZeroEventLoopLag(): void
    {
        $provider = new MetricsProvider();

        $metrics = $provider->fromMetricsData([
            'date' => '2020-01-01T00:00:00+00:00',
            'server' => [
                ...self::SERVER_METRICS,
                'event_loop_lag_ms' => 0.0,
                'event_loop_lag_max_ms' => 0.0,
                'event_loop_lag_avg_ms' => 0.0,
            ],
        ]);

        self::assertSame(0.0, $metrics->eventLoopLagMs());
        self::assertSame(0.0, $metrics->maxEventLoopLagMs());
        self::assertSame(0.0, $metrics->avgEventLoopLagMs());
    }

    public function testEventLoopLagIsNullWhenNotReportedByServer(): void
    {
        $provider = new MetricsProvider();

        $metrics = $provider->fromMetricsData([
            'date' => '2020-01-01T00:00:00+00:00',
            'server' => self::SERVER_METRICS,
        ]);

        self::assertNull($metrics->eventLoopLagMs());
        self::assertNull($metrics->maxEventLoopLagMs());
        self::assertNull($metrics->avgEventLoopLagMs());
    }
}

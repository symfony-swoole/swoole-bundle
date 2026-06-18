<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Swoole\Metrics;

use PHPUnit\Framework\TestCase;
use SwooleBundle\SwooleBundle\Bridge\Swoole\Metrics\MetricsProvider;

final class MetricsProviderTest extends TestCase
{
    public function testEventLoopLagIsNullBecauseSwooleDoesNotReportIt(): void
    {
        $provider = new MetricsProvider();

        $metrics = $provider->fromMetricsData([
            'date' => '2020-01-01T00:00:00+00:00',
            'server' => [
                'start_time' => 0,
                'worker_num' => 4,
                'idle_worker_num' => 1,
                'request_count' => 101,
                'connection_num' => 103,
                'accept_count' => 104,
                'abort_count' => 105,
                'coroutine_num' => 109,
                'tasking_num' => 110,
            ],
        ]);

        self::assertNull($metrics->eventLoopLagMs());
        self::assertNull($metrics->maxEventLoopLagMs());
        self::assertNull($metrics->avgEventLoopLagMs());
    }
}

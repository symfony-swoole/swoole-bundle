<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Metrics;

use SwooleBundle\SwooleBundle\Bridge\OpenSwoole\Metrics\MetricsProvider as OpenSwooleMetricsProvider;
use SwooleBundle\SwooleBundle\Bridge\Swoole\Metrics\MetricsProvider as SwooleMetricsProvider;

/**
 * @phpstan-import-type OpenSwooleMetricsShape from OpenSwooleMetricsProvider
 * @phpstan-import-type SwooleMetricsShape from SwooleMetricsProvider
 * @phpstan-type MetricsShape = OpenSwooleMetricsShape|SwooleMetricsShape
 */
interface MetricsProvider
{
    /**
     * @param MetricsShape $metricsData
     */
    public function fromMetricsData(array $metricsData): Metrics;
}

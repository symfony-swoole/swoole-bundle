<?php

declare(strict_types=1);

namespace K911\Swoole\Metrics;

interface MetricsProvider
{
    public function fromMetricsData(array $metricsData): Metrics;
}

<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Metrics;

interface MetricsProvider
{
    public function fromMetricsData(array $metricsData): Metrics;
}

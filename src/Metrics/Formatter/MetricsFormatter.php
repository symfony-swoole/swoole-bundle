<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Metrics\Formatter;

use SwooleBundle\SwooleBundle\Metrics\MetricsProvider;

/**
 * Formats server metrics into a negotiated textual representation.
 *
 * @phpstan-import-type MetricsShape from MetricsProvider
 */
interface MetricsFormatter
{
    public function supports(string $accept): bool;

    public function contentType(): string;

    /**
     * @param MetricsShape $metricsData
     */
    public function format(array $metricsData): string;
}

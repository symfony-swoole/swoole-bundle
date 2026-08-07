<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Metrics\Formatter;

use SwooleBundle\SwooleBundle\Metrics\MetricsProvider;

/**
 * Formats server metrics as JSON, preserving the raw metrics payload.
 *
 * @phpstan-import-type MetricsShape from MetricsProvider
 */
final class JsonMetricsFormatter implements MetricsFormatter
{
    private const string CONTENT_TYPE = 'application/json';

    public function supports(string $accept): bool
    {
        return str_contains($accept, self::CONTENT_TYPE);
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
        return json_encode($metricsData, JSON_THROW_ON_ERROR);
    }
}

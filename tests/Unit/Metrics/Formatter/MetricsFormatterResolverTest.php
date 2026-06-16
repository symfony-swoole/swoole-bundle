<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Metrics\Formatter;

use ArrayIterator;
use PHPUnit\Framework\TestCase;
use SwooleBundle\SwooleBundle\Metrics\Formatter\MetricsFormatter;
use SwooleBundle\SwooleBundle\Metrics\Formatter\MetricsFormatterResolver;

final class MetricsFormatterResolverTest extends TestCase
{
    public function testResolvesFirstSupportingFormatter(): void
    {
        $json = $this->formatter('application/json');
        $openMetrics = $this->formatter('application/openmetrics-text');
        $resolver = new MetricsFormatterResolver(new ArrayIterator([$openMetrics, $json]), $json);

        self::assertSame($openMetrics, $resolver->resolve('text/plain, application/openmetrics-text'));
        self::assertSame($json, $resolver->resolve('application/json'));
    }

    public function testFallsBackToDefaultWhenNoFormatterMatches(): void
    {
        $json = $this->formatter('application/json');
        $openMetrics = $this->formatter('application/openmetrics-text');
        $resolver = new MetricsFormatterResolver(new ArrayIterator([$openMetrics, $json]), $json);

        self::assertSame($json, $resolver->resolve('*/*'));
        self::assertSame($json, $resolver->resolve(''));
        self::assertSame($json, $resolver->resolve('text/html'));
    }

    private function formatter(string $supportedMediaType): MetricsFormatter
    {
        return new class ($supportedMediaType) implements MetricsFormatter {
            public function __construct(private readonly string $supportedMediaType) {}

            public function supports(string $accept): bool
            {
                return str_contains($accept, $this->supportedMediaType);
            }

            public function contentType(): string
            {
                return $this->supportedMediaType;
            }

            /**
             * @param array<mixed> $metricsData
             */
            public function format(array $metricsData): string
            {
                return $this->supportedMediaType;
            }
        };
    }
}

<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Metrics\Formatter;

use PHPUnit\Framework\TestCase;
use SwooleBundle\SwooleBundle\Metrics\Formatter\JsonMetricsFormatter;

final class JsonMetricsFormatterTest extends TestCase
{
    private const array RAW_METRICS = [
        'date' => '2020-01-01T00:00:00+00:00',
        'server' => [
            'start_time' => 0,
            'worker_num' => 4,
            'idle_worker_num' => 2,
            'request_count' => 10,
            'connection_num' => 3,
            'accept_count' => 5,
            'abort_count' => 1,
            'coroutine_num' => 7,
            'tasking_num' => 0,
        ],
    ];

    public function testContentTypeIsJson(): void
    {
        $formatter = new JsonMetricsFormatter();

        self::assertSame('application/json', $formatter->contentType());
    }

    public function testSupportsJsonAcceptHeader(): void
    {
        $formatter = new JsonMetricsFormatter();

        self::assertTrue($formatter->supports('application/json'));
        self::assertTrue($formatter->supports('text/html, application/json;q=0.9'));
        self::assertFalse($formatter->supports('application/openmetrics-text'));
        self::assertFalse($formatter->supports('*/*'));
        self::assertFalse($formatter->supports(''));
    }

    public function testFormatsRawMetricsAsJsonPreservingPayload(): void
    {
        $formatter = new JsonMetricsFormatter();

        self::assertSame(
            json_encode(self::RAW_METRICS, JSON_THROW_ON_ERROR),
            $formatter->format(self::RAW_METRICS),
        );
    }
}

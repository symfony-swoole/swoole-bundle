<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Server\Health;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SwooleBundle\SwooleBundle\Server\Health\HealthCheckResult;
use SwooleBundle\SwooleBundle\Server\Health\HealthReporter;
use SwooleBundle\SwooleBundle\Server\Health\HealthStatusTable;

#[CoversClass(HealthReporter::class)]
final class HealthReporterTest extends TestCase
{
    private const int NOW = 1_000;
    private const int STALENESS_THRESHOLD = 3;

    public function testWithoutRegisteredChecksTheStaticAnswerIsServed(): void
    {
        $report = (new HealthReporter(null, self::STALENESS_THRESHOLD))->report(self::NOW);

        self::assertSame(200, $report->statusCode);
        self::assertSame(['ok' => true], $report->body);
    }

    public function testHealthyChecksAreReportedAlongsideTheStaticAnswer(): void
    {
        $table = HealthStatusTable::forChecks(1);
        $table->record('database', HealthCheckResult::healthy());
        $table->completeSweep(self::NOW);

        $report = (new HealthReporter($table, self::STALENESS_THRESHOLD))->report(self::NOW);

        self::assertSame(200, $report->statusCode);
        self::assertSame(
            ['ok' => true, 'checks' => ['database' => ['ok' => true, 'detail' => '']]],
            $report->body,
        );
    }

    public function testAnUnhealthyCheckFailsTheReport(): void
    {
        $table = HealthStatusTable::forChecks(2);
        $table->record('database', HealthCheckResult::unhealthy('connection refused'));
        $table->record('queue', HealthCheckResult::healthy());
        $table->completeSweep(self::NOW);

        $report = (new HealthReporter($table, self::STALENESS_THRESHOLD))->report(self::NOW);

        self::assertSame(503, $report->statusCode);
        self::assertSame(
            [
                'ok' => false,
                'checks' => [
                    'database' => ['ok' => false, 'detail' => 'connection refused'],
                    'queue' => ['ok' => true, 'detail' => ''],
                ],
            ],
            $report->body,
        );
    }

    public function testASweepOlderThanTheThresholdIsStale(): void
    {
        $table = HealthStatusTable::forChecks(1);
        $table->record('database', HealthCheckResult::healthy());
        $table->completeSweep(self::NOW - self::STALENESS_THRESHOLD - 1);

        $report = (new HealthReporter($table, self::STALENESS_THRESHOLD))->report(self::NOW);

        self::assertSame(503, $report->statusCode);
        self::assertSame(
            [
                'ok' => false,
                'stale' => true,
                'checks' => ['database' => ['ok' => true, 'detail' => '']],
            ],
            $report->body,
        );
    }

    public function testASweepThatNeverCompletedIsStale(): void
    {
        $report = (new HealthReporter(HealthStatusTable::forChecks(1), self::STALENESS_THRESHOLD))
            ->report(self::NOW);

        self::assertSame(503, $report->statusCode);
        self::assertSame(['ok' => false, 'stale' => true, 'checks' => []], $report->body);
    }

    public function testADetailThatIsNotValidUtf8IsStillEncodable(): void
    {
        $table = HealthStatusTable::forChecks(1);
        $table->record('database', HealthCheckResult::unhealthy("handshake failed: \xB1\x31\xFF"));
        $table->completeSweep(self::NOW);

        $report = (new HealthReporter($table, self::STALENESS_THRESHOLD))->report(self::NOW);

        self::assertJson($report->json());
        self::assertStringContainsString('handshake failed', $report->json());
    }

    public function testASweepExactlyOnTheThresholdIsStillFresh(): void
    {
        $table = HealthStatusTable::forChecks(1);
        $table->record('database', HealthCheckResult::healthy());
        $table->completeSweep(self::NOW - self::STALENESS_THRESHOLD);

        $report = (new HealthReporter($table, self::STALENESS_THRESHOLD))->report(self::NOW);

        self::assertSame(200, $report->statusCode);
    }
}

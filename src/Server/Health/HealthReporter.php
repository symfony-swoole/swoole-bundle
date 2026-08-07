<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Health;

final readonly class HealthReporter
{
    public function __construct(
        private ?HealthStatusTable $table,
        private int $stalenessThreshold,
    ) {}

    public function report(int $now): HealthReport
    {
        if ($this->table === null) {
            return new HealthReport(200, ['ok' => true]);
        }

        $lastSweepAt = $this->table->lastSweepAt();
        $stale = $lastSweepAt === 0 || $now - $lastSweepAt > $this->stalenessThreshold;
        $checks = $this->table->results();

        $failing = false;

        foreach ($checks as $check) {
            if ($check['ok']) {
                continue;
            }

            $failing = true;

            break;
        }

        if ($stale) {
            return new HealthReport(503, ['ok' => false, 'stale' => true, 'checks' => $checks]);
        }

        return new HealthReport($failing ? 503 : 200, ['ok' => !$failing, 'checks' => $checks]);
    }
}

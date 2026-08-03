<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Health;

use Assert\Assertion;
use Swoole\Atomic\Long;
use Swoole\Table;

final readonly class HealthStatusTable
{
    private const int DETAIL_SIZE = 256;

    private const int MINIMUM_ROWS = 64;

    private function __construct(private Table $table, private Long $lastSweepAt) {}

    public static function forChecks(int $checkCount): self
    {
        $table = new Table(max(self::MINIMUM_ROWS, $checkCount * 2));
        $table->column('healthy', Table::TYPE_INT, 1);
        $table->column('detail', Table::TYPE_STRING, self::DETAIL_SIZE);
        $table->create();

        return new self($table, new Long(0));
    }

    public function record(string $name, HealthCheckResult $result): void
    {
        $this->table->set($name, [
            'healthy' => (int) $result->healthy,
            'detail' => mb_substr($result->detail, 0, self::DETAIL_SIZE - 1),
        ]);
    }

    public function completeSweep(int $timestamp): void
    {
        $this->lastSweepAt->set($timestamp);
    }

    public function lastSweepAt(): int
    {
        return $this->lastSweepAt->get();
    }

    /**
     * @return array<string, array{ok: bool, detail: string}>
     */
    public function results(): array
    {
        $results = [];

        /** @var array{healthy: int, detail: string} $row */
        foreach ($this->table as $name => $row) {
            Assertion::string($name);

            $results[$name] = [
                'ok' => (bool) $row['healthy'],
                'detail' => $row['detail'],
            ];
        }

        ksort($results);

        return $results;
    }
}

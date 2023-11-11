<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Swoole\Metrics;

use Assert\Assertion;
use K911\Swoole\Metrics\Metrics;
use K911\Swoole\Metrics\MetricsProvider as CommonMetricsProvider;

/**
 * @phpstan-type SwooleMetricsShape = array{
 *   date: string,
 *   server: array{
 *     start_time: int,
 *     worker_num: int,
 *     idle_worker_num: int,
 *     request_count: int,
 *     connection_num: int,
 *     accept_count: int,
 *     abort_count: int,
 *     coroutine_num: int,
 *     tasking_num?: int
 *   }
 * }
 */
final class MetricsProvider implements CommonMetricsProvider
{
    /**
     * @param SwooleMetricsShape $metricsData
     */
    public function fromMetricsData(array $metricsData): Metrics
    {
        $date = \DateTimeImmutable::createFromFormat(\DATE_ATOM, $metricsData['date']);
        Assertion::isInstanceOf($date, \DateTimeImmutable::class);
        $serverData = $metricsData['server'];
        $runningSeconds = $date->getTimestamp() - $serverData['start_time'];
        $totaWorkers = $serverData['worker_num'];
        $idleWorkers = $serverData['idle_worker_num'];
        $activeWorkers = $totaWorkers - $idleWorkers;

        return new Metrics(
            $serverData['request_count'],
            $runningSeconds,
            $serverData['connection_num'],
            $serverData['accept_count'],
            $serverData['abort_count'],
            $totaWorkers,
            $activeWorkers,
            $idleWorkers,
            $serverData['coroutine_num'],
            $serverData['tasking_num'] ?? 0,
        );
    }
}

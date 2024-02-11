<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\OpenSwoole\Metrics;

use Assert\Assertion;
use DateTimeImmutable;
use SwooleBundle\SwooleBundle\Metrics\Metrics;
use SwooleBundle\SwooleBundle\Metrics\MetricsProvider as CommonMetricsProvider;

/**
 * @phpstan-type OpenSwooleMetricsShape = array{
 *   date: string,
 *   server: array{
 *     start_time: int,
 *     workers_total: int,
 *     workers_idle: int,
 *     requests_total: int,
 *     connections_active: int,
 *     connections_accepted: int,
 *     connections_closed: int,
 *     coroutine_num: int,
 *     tasking_num: int
 *   }
 * }
 */
final class MetricsProvider implements CommonMetricsProvider
{
    /**
     * @param OpenSwooleMetricsShape $metricsData
     */
    public function fromMetricsData(array $metricsData): Metrics
    {
        $date = DateTimeImmutable::createFromFormat(DATE_ATOM, $metricsData['date']);
        Assertion::isInstanceOf($date, DateTimeImmutable::class);
        $serverData = $metricsData['server'];
        $runningSeconds = $date->getTimestamp() - $serverData['start_time'];
        $totaWorkers = $serverData['workers_total'];
        $idleWorkers = $serverData['workers_idle'];
        $activeWorkers = $totaWorkers - $idleWorkers;

        return new Metrics(
            $serverData['requests_total'],
            $runningSeconds,
            $serverData['connections_active'],
            $serverData['connections_accepted'],
            $serverData['connections_closed'],
            $totaWorkers,
            $activeWorkers,
            $idleWorkers,
            $serverData['coroutine_num'],
            $serverData['tasking_num']
        );
    }
}

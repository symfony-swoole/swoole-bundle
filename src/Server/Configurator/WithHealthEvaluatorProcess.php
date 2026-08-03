<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Configurator;

use Swoole\Http\Server;
use Swoole\Process;
use SwooleBundle\SwooleBundle\Server\Health\HealthCheck;
use SwooleBundle\SwooleBundle\Server\Health\HealthCheckResult;
use SwooleBundle\SwooleBundle\Server\Health\HealthStatusTable;
use Throwable;

final readonly class WithHealthEvaluatorProcess implements Configurator
{
    /**
     * @param iterable<HealthCheck> $checks
     */
    public function __construct(
        private HealthStatusTable $table,
        private iterable $checks,
        private int $interval,
    ) {}

    public function configure(Server $server): void
    {
        $table = $this->table;
        $checks = $this->checks;
        $interval = $this->interval;

        $server->addProcess(new Process(
            static function () use ($table, $checks, $interval): void {
                while (true) {
                    try {
                        foreach ($checks as $check) {
                            try {
                                $result = $check->check();
                            } catch (Throwable $exception) {
                                $result = HealthCheckResult::unhealthy($exception->getMessage());
                            }

                            $table->record($check->name(), $result);
                        }

                        $table->completeSweep(time());
                    } catch (Throwable) {
                    }

                    sleep($interval);
                }
            },
            false,
            SOCK_DGRAM,
            false,
        ));
    }
}

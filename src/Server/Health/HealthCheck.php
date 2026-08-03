<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Health;

/**
 * The evaluating process is forked from the server master. Anything a check touches must
 * be created inside {@see self::check()} rather than inherited from the parent, or the
 * resulting file descriptor is shared with the master and every worker.
 */
interface HealthCheck
{
    /**
     * Stable identifier, used as the key under which this check is reported. Two checks
     * answering the same name overwrite each other's verdict.
     */
    public function name(): string;

    public function check(): HealthCheckResult;
}

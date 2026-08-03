<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\HealthCheck;

use Override;
use SwooleBundle\SwooleBundle\Server\Health\HealthCheck;
use SwooleBundle\SwooleBundle\Server\Health\HealthCheckResult;

final readonly class BlockingHealthCheck implements HealthCheck
{
    public function __construct(private int $seconds) {}

    #[Override]
    public function name(): string
    {
        return 'blocking';
    }

    #[Override]
    public function check(): HealthCheckResult
    {
        sleep($this->seconds);

        return HealthCheckResult::healthy();
    }
}

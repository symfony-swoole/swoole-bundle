<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\HealthCheck;

use Override;
use RuntimeException;
use SwooleBundle\SwooleBundle\Server\Health\HealthCheck;
use SwooleBundle\SwooleBundle\Server\Health\HealthCheckResult;

final readonly class UnconstructableHealthCheck implements HealthCheck
{
    public function __construct()
    {
        throw new RuntimeException('this check cannot be built');
    }

    #[Override]
    public function name(): string
    {
        return 'unconstructable';
    }

    #[Override]
    public function check(): HealthCheckResult
    {
        return HealthCheckResult::healthy();
    }
}

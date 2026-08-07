<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\HealthCheck;

use Override;
use SwooleBundle\SwooleBundle\Server\Health\HealthCheck;
use SwooleBundle\SwooleBundle\Server\Health\HealthCheckResult;

final readonly class FlaggedHealthCheck implements HealthCheck
{
    public function __construct(private string $flagFile) {}

    #[Override]
    public function name(): string
    {
        return 'flagged';
    }

    #[Override]
    public function check(): HealthCheckResult
    {
        if (!file_exists($this->flagFile)) {
            return HealthCheckResult::healthy();
        }

        return HealthCheckResult::unhealthy(trim((string) file_get_contents($this->flagFile)));
    }
}

<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Health;

final readonly class HealthCheckResult
{
    private function __construct(public bool $healthy, public string $detail) {}

    public static function healthy(string $detail = ''): self
    {
        return new self(true, $detail);
    }

    public static function unhealthy(string $detail): self
    {
        return new self(false, $detail);
    }
}

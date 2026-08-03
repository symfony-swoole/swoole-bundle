<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Health;

use JsonException;

final readonly class HealthReport
{
    /**
     * @param array<string, mixed> $body
     */
    public function __construct(public int $statusCode, public array $body) {}

    /**
     * @throws JsonException
     */
    public function json(): string
    {
        return json_encode($this->body, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
    }
}

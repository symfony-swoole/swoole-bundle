<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Grpc\ValueObject;

/**
 * Value object representing a gRPC service name.
 */
final readonly class ServiceName
{
    private function __construct(
        private string $value,
    ) {
    }

    /**
     * Create from string value.
     */
    public static function fromString(string $value): self
    {
        if (trim($value) === '') {
            throw new \InvalidArgumentException('Service name cannot be empty');
        }

        // Normalize to ensure leading slash
        $normalized = str_starts_with($value, '/') ? $value : '/' . $value;

        return new self($normalized);
    }

    /**
     * Get the string value.
     */
    public function toString(): string
    {
        return $this->value;
    }

    /**
     * Get the service name without leading slash.
     */
    public function toStringWithoutLeadingSlash(): string
    {
        return ltrim($this->value, '/');
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function equals(ServiceName $other): bool
    {
        return $this->value === $other->value;
    }
}

<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Grpc\ValueObject;

/**
 * Value object representing a gRPC method name.
 */
final readonly class MethodName
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
            throw new \InvalidArgumentException('Method name cannot be empty');
        }

        return new self($value);
    }

    /**
     * Get the string value.
     */
    public function toString(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function equals(MethodName $other): bool
    {
        return $this->value === $other->value;
    }
}

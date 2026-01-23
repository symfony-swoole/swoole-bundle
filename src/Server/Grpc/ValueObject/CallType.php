<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Grpc\ValueObject;

use InvalidArgumentException;
use SwooleBundle\SwooleBundle\Server\Grpc\Constant;

/**
 * Value object representing a gRPC call type.
 */
enum CallType: int
{
    case UNARY = Constant::GRPC_CALL_TYPE_UNARY;
    case SERVER_STREAM = Constant::GRPC_CALL_TYPE_STREAM;

    /**
     * Check if this is a unary call.
     */
    public function isUnary(): bool
    {
        return $this === self::UNARY;
    }

    /**
     * Check if this is a server-streaming call.
     */
    public function isServerStream(): bool
    {
        return $this === self::SERVER_STREAM;
    }

    /**
     * Get the integer value.
     */
    public function toInt(): int
    {
        return $this->value;
    }

    /**
     * Create from integer value.
     */
    public static function fromInt(int $value): self
    {
        return match ($value) {
            Constant::GRPC_CALL_TYPE_UNARY => self::UNARY,
            Constant::GRPC_CALL_TYPE_STREAM => self::SERVER_STREAM,
            default => throw new InvalidArgumentException("Invalid call type: {$value}"),
        };
    }
}

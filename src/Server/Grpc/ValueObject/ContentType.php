<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Grpc\ValueObject;

use InvalidArgumentException;

/**
 * Value object representing a gRPC content type.
 */
enum ContentType: string
{
    case GRPC = 'application/grpc';
    case GRPC_PROTO = 'application/grpc+proto';
    case GRPC_JSON = 'application/grpc+json';

    /**
     * Check if this is a JSON content type.
     */
    public function isJson(): bool
    {
        return $this === self::GRPC_JSON;
    }

    /**
     * Check if this is a protobuf content type.
     */
    public function isProtobuf(): bool
    {
        return $this === self::GRPC || $this === self::GRPC_PROTO;
    }

    /**
     * Create from string value.
     *
     * @throws InvalidArgumentException if content type is not valid
     */
    public static function fromString(string $value): self
    {
        return match ($value) {
            'application/grpc' => self::GRPC,
            'application/grpc+proto' => self::GRPC_PROTO,
            'application/grpc+json' => self::GRPC_JSON,
            default => throw new InvalidArgumentException("Unsupported content type: {$value}"),
        };
    }

    /**
     * Get all valid content type strings.
     *
     * @return array<string>
     */
    public static function validTypes(): array
    {
        return [
            self::GRPC->value,
            self::GRPC_PROTO->value,
            self::GRPC_JSON->value,
        ];
    }
}

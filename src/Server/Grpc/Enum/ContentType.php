<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Grpc\Enum;

/**
 * gRPC content types.
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
     * Get all valid content type strings.
     *
     * @return array<string>
     */
    public static function validTypes(): array
    {
        return array_column(self::cases(), 'value');
    }
}

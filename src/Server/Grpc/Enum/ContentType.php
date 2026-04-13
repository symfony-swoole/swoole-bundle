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
}

<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Grpc\Enum;

/**
 * gRPC call types.
 */
enum CallType: int
{
    /**
     * Unary call type (single request, single response).
     */
    case UNARY = 1;

    /**
     * Server-streaming call type (single request, multiple responses).
     */
    case STREAM = 2;
}

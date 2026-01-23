<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Grpc;

/**
 * gRPC protocol constants.
 *
 * Contains standard gRPC headers, call types, and protocol-specific values.
 */
final class Constant
{
    /**
     * HTTP header name for content type.
     */
    public const CONTENT_TYPE = 'content-type';

    /**
     * gRPC status header name.
     */
    public const GRPC_STATUS = 'grpc-status';

    /**
     * gRPC message header name.
     */
    public const GRPC_MESSAGE = 'grpc-message';

    /**
     * Unary call type (single request, single response).
     */
    public const GRPC_CALL_TYPE_UNARY = 1;

    /**
     * Server-streaming call type (single request, multiple responses).
     */
    public const GRPC_CALL_TYPE_STREAM = 2;

    private function __construct()
    {
        // Prevent instantiation
    }
}

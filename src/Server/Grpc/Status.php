<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Grpc;

/**
 * gRPC status codes as defined in the gRPC specification.
 * todo: enum?!
 *
 * @see https://grpc.github.io/grpc/core/md_doc_statuscodes.html
 */
final class Status
{
    /**
     * Success status.
     */
    public const OK = 0;

    /**
     * The operation was cancelled.
     */
    public const CANCELLED = 1;

    /**
     * Unknown error.
     */
    public const UNKNOWN = 2;

    /**
     * Client specified an invalid argument.
     */
    public const INVALID_ARGUMENT = 3;

    /**
     * Deadline expired before operation could complete.
     */
    public const DEADLINE_EXCEEDED = 4;

    /**
     * Some requested entity was not found.
     */
    public const NOT_FOUND = 5;

    /**
     * Entity already exists.
     */
    public const ALREADY_EXISTS = 6;

    /**
     * Caller does not have permission.
     */
    public const PERMISSION_DENIED = 7;

    /**
     * Resource has been exhausted.
     */
    public const RESOURCE_EXHAUSTED = 8;

    /**
     * Operation rejected because system is not in required state.
     */
    public const FAILED_PRECONDITION = 9;

    /**
     * Operation was aborted.
     */
    public const ABORTED = 10;

    /**
     * Operation attempted past valid range.
     */
    public const OUT_OF_RANGE = 11;

    /**
     * Operation is not implemented or supported.
     */
    public const UNIMPLEMENTED = 12;

    /**
     * Internal server error.
     */
    public const INTERNAL = 13;

    /**
     * Service is currently unavailable.
     */
    public const UNAVAILABLE = 14;

    /**
     * Unrecoverable data loss or corruption.
     */
    public const DATA_LOSS = 15;

    /**
     * Request does not have valid authentication credentials.
     */
    public const UNAUTHENTICATED = 16;

    private function __construct()
    {
        // Prevent instantiation
    }
}

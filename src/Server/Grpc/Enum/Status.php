<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Grpc\Enum;

/**
 * gRPC status codes as defined in the gRPC specification.
 *
 * @see https://grpc.github.io/grpc/core/md_doc_statuscodes.html
 */
enum Status: int
{
    case OK = 0;
    case CANCELLED = 1;
    case UNKNOWN = 2;
    case INVALID_ARGUMENT = 3;
    case DEADLINE_EXCEEDED = 4;
    case NOT_FOUND = 5;
    case ALREADY_EXISTS = 6;
    case PERMISSION_DENIED = 7;
    case RESOURCE_EXHAUSTED = 8;
    case FAILED_PRECONDITION = 9;
    case ABORTED = 10;
    case OUT_OF_RANGE = 11;
    case UNIMPLEMENTED = 12;
    case INTERNAL = 13;
    case UNAVAILABLE = 14;
    case DATA_LOSS = 15;
    case UNAUTHENTICATED = 16;

    public static function fromHttpStatus(int $httpStatus): self
    {
        return match ($httpStatus) {
            400 => self::INVALID_ARGUMENT,
            401 => self::UNAUTHENTICATED,
            403 => self::PERMISSION_DENIED,
            404 => self::NOT_FOUND,
            408 => self::DEADLINE_EXCEEDED,
            409 => self::ABORTED,
            412 => self::FAILED_PRECONDITION,
            429 => self::RESOURCE_EXHAUSTED,
            501 => self::UNIMPLEMENTED,
            502, 503 => self::UNAVAILABLE,
            504 => self::DEADLINE_EXCEEDED,
            default => $httpStatus >= 500 ? self::INTERNAL : self::UNKNOWN,
        };
    }
}

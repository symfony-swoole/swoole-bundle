<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Grpc\Exception;

use RuntimeException;
use SwooleBundle\SwooleBundle\Server\Grpc\Enum\Status;
use Throwable;

/**
 * Base exception for gRPC errors, providing a static factory for creation.
 */
class GRPCException extends RuntimeException
{
    protected static Status $statusCode = Status::UNKNOWN;

    /**
     * GRPCException constructor.
     */
    final public function __construct(
        string $message = '',
        ?Status $statusCode = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, ($statusCode ?? static::$statusCode)->value, $previous);
    }

    /**
     * Create a new GRPCException instance.
     */
    public static function create(string $message, ?Status $statusCode = null, ?Throwable $previous = null): static
    {
        return new static($message, $statusCode, $previous);
    }
}

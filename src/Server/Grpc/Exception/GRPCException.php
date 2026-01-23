<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Grpc\Exception;

use RuntimeException;
use SwooleBundle\SwooleBundle\Server\Grpc\Status;
use Throwable;

/**
 * Class GRPCException
 *
 * Base exception for gRPC errors, providing a static factory for creation.
 */
class GRPCException extends RuntimeException
{
    protected const CODE = Status::UNKNOWN;

    /**
     * GRPCException constructor.
     */
    public function __construct(
        string $message = '',
        ?int $code = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, (int) ($code ?? self::CODE), $previous);
    }

    /**
     * Create a new GRPCException instance.
     *
     * @return static
     */
    public static function create(string $message, ?int $code = null, ?Throwable $previous = null,): self {
        return new static($message, $code, $previous);
    }
}

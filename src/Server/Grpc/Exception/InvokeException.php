<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Grpc\Exception;

use SwooleBundle\SwooleBundle\Server\Grpc\Enum\Status;

/**
 * Class InvokeException
 *
 * Exception thrown when a gRPC invocation fails.
 */
class InvokeException extends GRPCException
{
    protected static Status $statusCode = Status::UNAVAILABLE;
}

<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Grpc\Exception;

use SwooleBundle\SwooleBundle\Server\Grpc\Status;

/**
 * Class ServiceException
 *
 * Exception thrown for internal gRPC service errors.
 */
class ServiceException extends GRPCException
{
    protected const CODE = Status::INTERNAL;
}

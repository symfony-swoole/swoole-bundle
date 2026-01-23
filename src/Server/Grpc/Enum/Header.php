<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Grpc\Enum;

/**
 * gRPC protocol header constants.
 */
enum Header: string
{
    case CONTENT_TYPE = 'content-type';
    case GRPC_MESSAGE = 'grpc-message';
    case GRPC_STATUS = 'grpc-status';
    case TRAILER = 'trailer';
}

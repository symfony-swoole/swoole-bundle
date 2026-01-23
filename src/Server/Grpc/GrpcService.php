<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Grpc;

/**
 * Marker interface for gRPC service classes.
 *
 * All gRPC services must implement this interface and define a NAME constant
 * representing the fully-qualified service name (e.g., '/myapp.MyService').
 *
 * Or attribute: #[GrpcService] can be used @see src/Server/Grpc/Attribute/GrpcService.php
 */
interface GrpcService
{
    /**
     * The fully-qualified name of the gRPC service.
     * Must start with '/' and follow the format '/package.ServiceName'
     *
     * Example: '/myapp.UserService'
     */
    public const NAME = '';
}

<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Grpc\CallHandler;

use Google\Protobuf\Internal\Message;
use SwooleBundle\SwooleBundle\Server\Grpc\Context\Context;
use SwooleBundle\SwooleBundle\Server\Grpc\Service\ServiceMethodDefinition;

/**
 * Interface for handling different types of gRPC calls.
 */
interface CallHandler
{
    /**
     * Handle a gRPC call.
     *
     * @param Context $context The gRPC context
     * @param object $service The service instance (can implement GrpcService or use #[GrpcService])
     * @param ServiceMethodDefinition $methodDefinition The method definition
     * @param Message $message The deserialized request message
     * @return Context The updated context with response
     */
    public function handle(
        Context $context,
        object $service,
        ServiceMethodDefinition $methodDefinition,
        Message $message,
    ): Context;

    /**
     * Check if this handler can handle the given method definition.
     */
    public function supports(ServiceMethodDefinition $methodDefinition): bool;
}

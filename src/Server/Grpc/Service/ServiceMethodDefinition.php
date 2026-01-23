<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Grpc\Service;

use SwooleBundle\SwooleBundle\Server\Grpc\Constant;

/**
 * Represents the definition of a gRPC service method,
 * including its name, parameter type, return type, and streaming information.
 */
final readonly class ServiceMethodDefinition
{
    /**
     * ServiceMethodDefinition constructor.
     *
     * @param string $name The method name.
     * @param class-string $paramType The parameter type (protobuf message class).
     * @param class-string $returnType The return type (protobuf message class).
     * @param int $type The method type (GRPC_CALL_TYPE_UNARY or GRPC_CALL_TYPE_STREAM).
     * @param class-string|null $streamType The stream type class, if applicable.
     */
    public function __construct(
        public string $name,
        public string $paramType,
        public string $returnType,
        public int $type = Constant::GRPC_CALL_TYPE_UNARY,
        public ?string $streamType = null,
    ) {
    }
}

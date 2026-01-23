<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Grpc\Attribute;

use Attribute;

/**
 * Attribute to mark a method as a gRPC method with optional metadata.
 *
 * This is optional - methods are auto-discovered if they follow gRPC signature pattern.
 * Use this attribute to provide additional metadata or override defaults.
 *
 * Example:
 * #[GrpcMethod(name: 'GetUser')]
 * public function getUser(ContextInterface $context, GetUserRequest $request): GetUserResponse
 */
#[Attribute(Attribute::TARGET_METHOD)]
final readonly class GrpcMethod
{
    /**
     * @param string|null $name Custom method name. If null, uses actual method name.
     */
    public function __construct(
        public ?string $name = null,
    ) {
    }
}

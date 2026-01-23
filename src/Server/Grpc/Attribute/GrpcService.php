<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Grpc\Attribute;

use Attribute;

/**
 * Attribute to mark a class as a gRPC service.
 *
 * Can be used instead of implementing GrpcService interface.
 * Service name is automatically determined from class name if not specified.
 *
 * Examples:
 * - #[GrpcService] - Auto-generates name from class (MyService -> /MyService)
 * - #[GrpcService(name: 'UserService')] - Custom service name
 * - #[GrpcService(package: 'myapp')] - Sets package (myapp.UserService)
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class GrpcService
{
    /**
     * @param string|null $name Custom service name (without leading slash). If null, uses class name.
     * @param string|null $package Package name. Overrides global package configuration.
     */
    public function __construct(
        public ?string $name = null,
        public ?string $package = null,
    ) {
    }
}

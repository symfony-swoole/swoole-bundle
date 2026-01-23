<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Grpc\Service;

use ReflectionClass;
use SwooleBundle\SwooleBundle\Server\Grpc\Attribute\GrpcService as GrpcServiceAttribute;
use SwooleBundle\SwooleBundle\Server\Grpc\GrpcService as GrpcServiceInterface;

/**
 * Resolves service names from class metadata.
 *
 * Supports multiple strategies:
 * 1. Attribute with explicit name: #[GrpcService(name: 'UserService')]
 * 2. Attribute with package: #[GrpcService(package: 'myapp')]
 * 3. Interface constant: implements GrpcService { const NAME = '/myapp.UserService'; }
 * 4. Auto-generated from class name
 */
final readonly class ServiceNameResolver
{
    public function __construct(
        private ?string $defaultPackage = null,
    ) {
    }

    /**
     * Resolve the fully-qualified service name for a service instance.
     *
     * @param object $service The service instance
     * @return string The fully-qualified service name (e.g., '/myapp.UserService')
     */
    public function resolve(object $service): string
    {
        $reflection = new ReflectionClass($service);

        // Strategy 1: Check for GrpcService attribute
        $attributes = $reflection->getAttributes(GrpcServiceAttribute::class);
        if (count($attributes) > 0) {
            /** @var GrpcServiceAttribute $attribute */
            $attribute = $attributes[0]->newInstance();

            return $this->resolveFromAttribute($reflection, $attribute);
        }

        // Strategy 2: Check for GrpcService interface with NAME constant
        if ($service instanceof GrpcServiceInterface && $service::NAME !== '') {
            return $this->normalizeServiceName($service::NAME);
        }

        // Strategy 3: Auto-generate from class name
        return $this->generateFromClassName($reflection);
    }

    /**
     * Resolve service name from attribute.
     */
    private function resolveFromAttribute(ReflectionClass $reflection, GrpcServiceAttribute $attribute): string
    {
        // Use explicit name from attribute if provided
        $serviceName = $attribute->name ?? $reflection->getShortName();

        // Determine package
        $package = $attribute->package ?? $this->defaultPackage;

        // Build fully-qualified name
        if ($package !== null && $package !== '') {
            return '/' . $package . '.' . $serviceName;
        }

        return '/' . $serviceName;
    }

    /**
     * Generate service name from class name.
     */
    private function generateFromClassName(ReflectionClass $reflection): string
    {
        $className = $reflection->getShortName();

        if ($this->defaultPackage !== null && $this->defaultPackage !== '') {
            return '/' . $this->defaultPackage . '.' . $className;
        }

        return '/' . $className;
    }

    /**
     * Normalize service name to ensure it starts with '/'.
     */
    private function normalizeServiceName(string $name): string
    {
        return str_starts_with($name, '/') ? $name : '/' . $name;
    }
}

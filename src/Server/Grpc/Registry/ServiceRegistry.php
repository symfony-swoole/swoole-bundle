<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Grpc\Registry;

use SwooleBundle\SwooleBundle\Server\Grpc\Exception\ServiceException;
use SwooleBundle\SwooleBundle\Server\Grpc\Service\ServiceMethodDefinition;
use SwooleBundle\SwooleBundle\Server\Grpc\ValueObject\MethodName;
use SwooleBundle\SwooleBundle\Server\Grpc\ValueObject\ServiceName;

/**
 * Registry for gRPC services and their methods.
 *
 * Provides type-safe storage and retrieval of services and method definitions.
 * Supports both interface-based and attribute-based service registration.
 * Groups services and methods by package for better organization.
 */
final class ServiceRegistry
{
    /**
     * @var array<string, object>
     */
    private array $services = [];

    /**
     * @var array<string, array<string, ServiceMethodDefinition>>
     */
    private array $methods = [];

    /**
     * Package index: maps package names to service names
     *
     * @var array<string|null, array<string>>
     */
    private array $packageIndex = [];

    /**
     * Maps service names to their package
     *
     * @var array<string, string|null>
     */
    private array $servicePackages = [];

    /**
     * Register a service with its method definitions.
     *
     * @param object $service The service instance
     * @param string $serviceName The fully-qualified service name (e.g., '/myapp.UserService' or '/UserService')
     * @param array<string, ServiceMethodDefinition> $methodDefinitions
     */
    public function register(object $service, string $serviceName, array $methodDefinitions): void
    {
        if (isset($this->services[$serviceName])) {
            throw new ServiceException("Service already registered: {$serviceName}");
        }

        // Extract package from service name
        $package = $this->extractPackage($serviceName);

        $this->services[$serviceName] = $service;
        $this->methods[$serviceName] = $methodDefinitions;
        $this->servicePackages[$serviceName] = $package;

        // Index by package
        if (!isset($this->packageIndex[$package])) {
            $this->packageIndex[$package] = [];
        }
        $this->packageIndex[$package][] = $serviceName;
    }

    /**
     * Extract package name from fully-qualified service name.
     *
     * @param string $serviceName e.g., '/myapp.UserService' or '/UserService'
     * @return string|null Package name or null if no package
     */
    public function extractPackage(string $serviceName): ?string
    {
        $name = ltrim($serviceName, '/');

        if (str_contains($name, '.')) {
            $parts = explode('.', $name);

            return implode('.', array_slice($parts, 0, -1));
        }

        return null;
    }

    /**
     * Check if a package exists in the registry.
     */
    public function hasPackage(?string $package): bool
    {
        return isset($this->packageIndex[$package]);
    }

    /**
     * Get all services in a specific package.
     *
     * @return array<string> Array of service names
     */
    public function getServicesByPackage(?string $package): array
    {
        return $this->packageIndex[$package] ?? [];
    }

    /**
     * Get the package for a given service.
     */
    public function getServicePackage(string $serviceName): ?string
    {
        return $this->servicePackages[$serviceName] ?? null;
    }

    /**
     * Get all registered packages.
     *
     * @return array<string|null> Array of package names
     */
    public function getAllPackages(): array
    {
        return array_keys($this->packageIndex);
    }

    /**
     * Validate that a service belongs to the expected package.
     *
     * @throws ServiceException if package validation fails
     */
    public function validateServicePackage(string $serviceName, ?string $expectedPackage = null): void
    {
        if (!$this->hasService($serviceName)) {
            throw new ServiceException("Cannot validate package: service not found: {$serviceName}");
        }

        $actualPackage = $this->servicePackages[$serviceName] ?? null;

        if ($expectedPackage !== null && $actualPackage !== $expectedPackage) {
            throw new ServiceException(
                "Package mismatch for service '{$serviceName}': expected '{$expectedPackage}', got '{$actualPackage}'"
            );
        }
    }

    /**
     * Check if a service is registered.
     */
    public function hasService(ServiceName|string $serviceName): bool
    {
        $name = $serviceName instanceof ServiceName ? $serviceName->toString() : $serviceName;

        return isset($this->services[$name]);
    }

    /**
     * Get a registered service.
     *
     * @throws ServiceException if service not found
     */
    public function getService(ServiceName|string $serviceName): object
    {
        $name = $serviceName instanceof ServiceName ? $serviceName->toString() : $serviceName;

        if (!isset($this->services[$name])) {
            throw new ServiceException("Service not found: {$name}");
        }

        return $this->services[$name];
    }

    /**
     * Check if a method exists for a service.
     */
    public function hasMethod(ServiceName|string $serviceName, MethodName|string $methodName): bool
    {
        $service = $serviceName instanceof ServiceName ? $serviceName->toString() : $serviceName;
        $method = $methodName instanceof MethodName ? $methodName->toString() : $methodName;

        return isset($this->methods[$service][$method]);
    }

    /**
     * Get a method definition.
     *
     * @throws ServiceException if method not found
     */
    public function getMethod(ServiceName|string $serviceName, MethodName|string $methodName): ServiceMethodDefinition
    {
        $service = $serviceName instanceof ServiceName ? $serviceName->toString() : $serviceName;
        $method = $methodName instanceof MethodName ? $methodName->toString() : $methodName;

        if (!isset($this->methods[$service][$method])) {
            throw new ServiceException("Method not found: {$service}/{$method}");
        }

        return $this->methods[$service][$method];
    }

    /**
     * Get all registered services.
     *
     * @return array<string, object>
     */
    public function getAllServices(): array
    {
        return $this->services;
    }

    /**
     * Get all methods for a service.
     *
     * @return array<string, ServiceMethodDefinition>
     */
    public function getServiceMethods(ServiceName|string $serviceName): array
    {
        $name = $serviceName instanceof ServiceName ? $serviceName->toString() : $serviceName;

        return $this->methods[$name] ?? [];
    }

    /**
     * Get the total number of registered services.
     */
    public function count(): int
    {
        return count($this->services);
    }
}

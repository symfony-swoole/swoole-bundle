<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Grpc\Router;

use SwooleBundle\SwooleBundle\Server\Grpc\Exception\InvokeException;
use SwooleBundle\SwooleBundle\Server\Grpc\Registry\ServiceRegistry;
use SwooleBundle\SwooleBundle\Server\Grpc\Service\ServiceMethodDefinition;
use SwooleBundle\SwooleBundle\Server\Grpc\Status;
use SwooleBundle\SwooleBundle\Server\Grpc\ValueObject\MethodName;
use SwooleBundle\SwooleBundle\Server\Grpc\ValueObject\ServiceName;

/**
 * Router for resolving gRPC service methods with package validation.
 */
final readonly class ServiceRouter
{
    public function __construct(
        private ServiceRegistry $registry,
    ) {
    }

    /**
     * Route a request to the appropriate service and method.
     * Validates that the service exists and optionally validates package.
     *
     * @return array{0: object, 1: ServiceMethodDefinition}
     * @throws InvokeException if service or method not found or package validation fails
     */
    public function route(ServiceName|string $serviceName, MethodName|string $methodName): array
    {
        $service = $serviceName instanceof ServiceName ? $serviceName->toString() : $serviceName;
        $method = $methodName instanceof MethodName ? $methodName->toString() : $methodName;

        if (!$this->registry->hasService($service)) {
            $requestedPackage = $this->registry->extractPackage($service);

            if ($requestedPackage !== null) {
                if (!$this->registry->hasPackage($requestedPackage)) {
                    throw InvokeException::create(
                        "Package not found: '{$requestedPackage}'. Service '{$service}' is not registered.",
                        Status::NOT_FOUND
                    );
                }

                throw InvokeException::create(
                    "Service not found: '{$service}'. Requested package: '{$requestedPackage}'.",
                    Status::NOT_FOUND
                );
            }

            throw InvokeException::create("Service not found: {$service}", Status::NOT_FOUND);
        }

        if (!$this->registry->hasMethod($service, $method)) {
            throw InvokeException::create("Method not found: {$service}/{$method}", Status::NOT_FOUND);
        }

        return [
            $this->registry->getService($service),
            $this->registry->getMethod($service, $method),
        ];
    }
}

<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Grpc\Service;

use Psr\Container\ContainerInterface;
use SwooleBundle\SwooleBundle\Server\Grpc\CallHandler\CallHandler;
use SwooleBundle\SwooleBundle\Server\Grpc\Context\Context;
use SwooleBundle\SwooleBundle\Server\Grpc\Context\ContextKeys;
use SwooleBundle\SwooleBundle\Server\Grpc\Exception\ServiceException;
use SwooleBundle\SwooleBundle\Server\Grpc\Interceptor\InterceptorChain;
use SwooleBundle\SwooleBundle\Server\Grpc\Registry\ServiceRegistry;
use SwooleBundle\SwooleBundle\Server\Grpc\Router\ServiceRouter;
use SwooleBundle\SwooleBundle\Server\Grpc\Serialization\PayloadDeserializer;

/**
 * Uses Registry, Router, CallHandler strategy, and Interceptor pattern.
 * Supports both interface-based and attribute-based service configuration.
 */
final class ServiceHandler
{
    private ServiceRegistry $registry;
    private ServiceRouter $router;
    private ServiceNameResolver $nameResolver;

    /**
     * @var array<CallHandler>
     */
    private array $callHandlers = [];

    /**
     * ServiceHandler constructor.
     *
     * @param iterable<object> $services List of service instances.
     * @param ContainerInterface|null $container Optional PSR container for dependency resolution.
     * @param PayloadDeserializer $deserializer Payload deserializer
     * @param iterable<CallHandler> $callHandlers Call handlers for different call types
     * @param InterceptorChain|null $interceptorChain Optional interceptor chain
     * @param string|null $defaultPackage Default package name for services (e.g., 'myapp')
     * @param bool $interceptorsEnabled Whether to use interceptors (default: false)
     */
    public function __construct(
        iterable $services,
        private ?ContainerInterface $container,
        private readonly PayloadDeserializer $deserializer,
        iterable $callHandlers = [],
        private readonly ?InterceptorChain $interceptorChain = null,
        ?string $defaultPackage = null,
        private readonly bool $interceptorsEnabled = false,
    ) {
        $this->registry = new ServiceRegistry();
        $this->router = new ServiceRouter($this->registry);
        $this->nameResolver = new ServiceNameResolver($defaultPackage);

        foreach ($callHandlers as $handler) {
            $this->callHandlers[] = $handler;
        }

        foreach ($services as $service) {
            $this->addService($service);
        }
    }

    /**
     * Resolve a service from the container or instantiate it directly.
     *
     * @template T
     * @param class-string<T>|string $abstract
     * @return T
     */
    public function resolve(string $abstract)
    {
        if ($this->container && $this->container->has($abstract)) {
            return $this->container->get($abstract);
        }

        return new $abstract();
    }

    /**
     * Get the service registry.
     */
    public function getRegistry(): ServiceRegistry
    {
        return $this->registry;
    }

    /**
     * Get the service router.
     */
    public function getRouter(): ServiceRouter
    {
        return $this->router;
    }

    /**
     * Add a service instance to the handler.
     *
     * Supports both interface-based (GrpcService) and attribute-based (#[GrpcService]) services.
     */
    public function addService(object $service): self
    {
        $serviceName = $this->nameResolver->resolve($service);
        $methods = $this->discoverMethods($service);
        $this->registry->register($service, $serviceName, $methods);

        return $this;
    }

    /**
     * Handle a gRPC request by dispatching to the appropriate service method.
     */
    public function handle(Context $context): Context
    {
        $serviceName = $context->getRequest()->getService();
        $methodName = $context->getRequest()->getMethod();

        // Route the request to find service and method definition
        [$service, $methodDefinition] = $this->router->route($serviceName, $methodName);

        // Store method definition in context
        $context->withAttribute(ContextKeys::SERVICE_METHOD_DEFINITION, $methodDefinition);

        // Deserialize the request payload
        $payload = $context->getRequest()->getPayload();
        $message = $this->deserializer->deserialize($payload, $methodDefinition->paramType, $context);

        // Find appropriate call handler
        $callHandler = $this->findCallHandler($methodDefinition);

        // Define the handler callable
        $handler = static fn(Context $ctx) => $callHandler->handle($ctx, $service, $methodDefinition, $message);

        // Execute with interceptor chain if enabled and available
        if ($this->interceptorsEnabled && $this->interceptorChain !== null) {
            return $this->interceptorChain->execute($context, $handler);
        }

        return $handler($context);
    }

    /**
     * Find the appropriate call handler for a method definition.
     *
     * @throws ServiceException if no handler found
     */
    private function findCallHandler(ServiceMethodDefinition $methodDefinition): CallHandler
    {
        foreach ($this->callHandlers as $handler) {
            if ($handler->supports($methodDefinition)) {
                return $handler;
            }
        }

        throw new ServiceException("No call handler found for method type: {$methodDefinition->type}");
    }

    /**
     * Discover gRPC methods on the given service instance.
     *
     * @return array<string, ServiceMethodDefinition>
     */
    private function discoverMethods(object $instance): array
    {
        return (new ServiceMethodScanner($instance))->getMethods();
    }
}

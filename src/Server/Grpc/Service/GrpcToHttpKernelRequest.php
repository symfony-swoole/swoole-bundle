<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Grpc\Service;

use RuntimeException;
use SwooleBundle\SwooleBundle\Server\Grpc\Attribute\GrpcService;
use SwooleBundle\SwooleBundle\Server\Grpc\Context\ContextInterface;
use SwooleBundle\SwooleBundle\Server\Grpc\Context\ContextKeys;
use SwooleBundle\SwooleBundle\Server\Grpc\Factory\HttpFoundationFactory;
use SwooleBundle\SwooleBundle\Server\Grpc\Generated\Psr7Request;
use SwooleBundle\SwooleBundle\Server\Grpc\Generated\Psr7Response;
use Throwable;

/**
 * In development, testing phase, can be changed or removed.
 *
 * gRPC service that bridges PSR-7 requests to Symfony's HTTP Kernel.
 *
 * Flow:
 * 1. GrpcServerRequestHandler gets kernel from pool and stores in context
 * 2. This service method converts and executes through kernel
 * 3. GrpcServerRequestHandler sends response, terminates kernel, returns to pool
 */
#[GrpcService(package: 'SwooleBundle')]
final class GrpcToHttpKernelRequest
{
    public const SERVICE_NAME = '/SwooleBundle.GrpcToHttpKernelRequest';

    public function __construct(
        private readonly HttpFoundationFactory $httpFoundationFactory,
    ) {
    }

    /**
     * Handle a PSR-7 request through Symfony kernel.
     *
     * The kernel is managed by GrpcServerRequestHandler which:
     * - Gets kernel from pool and stores in context before this method
     * - Writes response after this method returns
     * - Terminates kernel after response is sent
     * - Returns kernel to pool
     *
     * @throws RuntimeException if kernel or request is not available
     */
    public function HandleRequest(ContextInterface $ctx, Psr7Request $psr7Request): Psr7Response
    {
        // Get kernel from context (set by GrpcServerRequestHandler)
        $kernel = $ctx->getAttribute(ContextKeys::SYMFONY_KERNEL);

        if ($kernel === null) {
            throw new RuntimeException('No kernel available in context');
        }

        // Convert PSR-7 request to HttpFoundation request
        try {
            $httpFoundationRequest = $this->httpFoundationFactory->make($psr7Request);
        } catch (Throwable $e) {
            throw new RuntimeException(
                sprintf('Failed to convert PSR-7 request: %s', $e->getMessage()),
                0,
                $e
            );
        }

        // Execute through Symfony kernel
        $httpFoundationResponse = $kernel->handle($httpFoundationRequest);

        // Convert response back to PSR-7
        try {
            $psr7Response = $this->httpFoundationFactory->convertResponse($httpFoundationResponse);
        } catch (Throwable $e) {
            throw new RuntimeException(
                sprintf('Failed to convert response to PSR-7: %s', $e->getMessage()),
                0,
                $e
            );
        }

        // Store HTTP objects in context for GrpcServerRequestHandler to terminate kernel
        $ctx->withAttribute(ContextKeys::SYMFONY_HTTP_REQUEST, $httpFoundationRequest);
        $ctx->withAttribute(ContextKeys::SYMFONY_HTTP_RESPONSE, $httpFoundationResponse);

        return $psr7Response;
    }
}

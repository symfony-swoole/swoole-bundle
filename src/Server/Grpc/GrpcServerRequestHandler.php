<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Grpc;

use Swoole\Http\Request;
use Swoole\Http\Response;
use SwooleBundle\SwooleBundle\Bridge\Symfony\HttpKernel\KernelPool;
use SwooleBundle\SwooleBundle\Server\Grpc\Context\Context;
use SwooleBundle\SwooleBundle\Server\Grpc\Context\ContextKeys;
use SwooleBundle\SwooleBundle\Server\Grpc\Exception\GRPCException;
use SwooleBundle\SwooleBundle\Server\Grpc\Factory\ContextFactory;
use SwooleBundle\SwooleBundle\Server\Grpc\Service\GrpcToHttpKernelRequest;
use SwooleBundle\SwooleBundle\Server\Grpc\Service\ServiceHandler;
use SwooleBundle\SwooleBundle\Server\Grpc\Writer\ResponseWriter;
use SwooleBundle\SwooleBundle\Server\HttpServer;
use SwooleBundle\SwooleBundle\Server\RequestHandler\RequestHandler;
use SwooleBundle\SwooleBundle\Server\Runtime\Bootable;
use Symfony\Component\HttpFoundation\Request as HttpFoundationRequest;
use Symfony\Component\HttpFoundation\Response as HttpFoundationResponse;
use Symfony\Component\HttpKernel\TerminableInterface;

/**
 * gRPC request handler with kernel lifecycle management.
 *
 * For PSR-7 bridge requests, this handler:
 * 1. Gets kernel from pool after context init
 * 2. Stores kernel in context for services to use
 * 3. Writes response (sends to client)
 * 4. Terminates kernel (async cleanup)
 * 5. Returns kernel to pool
 *
 * @phpstan-import-type RuntimeConfiguration from Bootable
 */
final readonly class GrpcServerRequestHandler implements RequestHandler, Bootable
{
    public function __construct(
        private HttpServer $server,
        private ServiceHandler $serviceHandler,
        private ResponseWriter $responseWriter,
        private ContextFactory $contextFactory,
        private KernelPool $kernelPool,
    ) {
    }

    /**
     * Boot the kernel pool before server starts.
     *
     * @param RuntimeConfiguration $runtimeConfiguration
     */
    public function boot(array $runtimeConfiguration = []): void
    {
        $this->kernelPool->boot();
    }

    public function handle(Request $request, Response $response): void
    {
        $context = $this->contextFactory->createContext($this->server, $request, $response);
        $context->init();

        // Check if this is a PSR-7 bridge request that needs kernel handling
        if ($context->getRequest()->getService() === GrpcToHttpKernelRequest::SERVICE_NAME) {
            $this->handleWithKernel($context);
        } else {
            $this->handleStandard($context);
        }
    }

    /**
     * Handle PSR-7 bridge requests with Symfony kernel lifecycle management.
     */
    private function handleWithKernel(Context $context): void
    {
        $kernel = $this->kernelPool->get();

        try {
            // Store kernel in context for the service to use
            $context = $context->withAttribute(ContextKeys::SYMFONY_KERNEL, $kernel);

            $this->handleStandard($context);

            if ($kernel instanceof TerminableInterface) {
                $httpRequest = $context->getAttribute(ContextKeys::SYMFONY_HTTP_REQUEST);
                $httpResponse = $context->getAttribute(ContextKeys::SYMFONY_HTTP_RESPONSE);

                if ($httpRequest instanceof HttpFoundationRequest && $httpResponse instanceof HttpFoundationResponse) {
                    $kernel->terminate($httpRequest, $httpResponse);
                }
            }
        } finally {
            $this->kernelPool->return($kernel);
        }
    }

    /**
     * Handle standard gRPC requests without kernel.
     */
    private function handleStandard(Context $context): void
    {
        try {
            $context = $this->serviceHandler->handle($context);
        } catch (GRPCException $e) {
            $context
                ->getResponse()
                ->withMessage($e->getMessage())
                ->withStatus($e->getCode());
        }

        $this->responseWriter->write($context);
    }
}

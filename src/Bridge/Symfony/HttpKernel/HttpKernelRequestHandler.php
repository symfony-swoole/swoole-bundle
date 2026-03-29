<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\HttpKernel;

use Exception;
use Swoole\Http\Request as SwooleRequest;
use Swoole\Http\Response as SwooleResponse;
use SwooleBundle\SwooleBundle\Bridge\Symfony\HttpFoundation\RequestFactory;
use SwooleBundle\SwooleBundle\Bridge\Symfony\HttpFoundation\ResponseProcessor;
use SwooleBundle\SwooleBundle\Bridge\Symfony\HttpFoundation\ResponseProcessorInjector;
use SwooleBundle\SwooleBundle\Server\RequestHandler\RequestHandler;
use SwooleBundle\SwooleBundle\Server\Runtime\Bootable;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\HttpKernel\TerminableInterface;

/**
 * @phpstan-import-type RuntimeConfiguration from Bootable
 */
final readonly class HttpKernelRequestHandler implements RequestHandler, Bootable
{
    public function __construct(
        private KernelInterface $kernel,
        private RequestFactory $requestFactory,
        private ResponseProcessorInjector $processorInjector,
        private ResponseProcessor $responseProcessor,
    ) {}

    /**
     * @param RuntimeConfiguration $runtimeConfiguration
     */
    public function boot(array $runtimeConfiguration = []): void
    {
        $this->kernel->boot();
    }

    /**
     * @throws Exception
     */
    public function handle(SwooleRequest $request, SwooleResponse $response): void
    {
        $httpFoundationRequest = $this->requestFactory->make($request);
        $this->processorInjector->injectProcessor($httpFoundationRequest, $response);
        $httpFoundationResponse = $this->kernel->handle($httpFoundationRequest);
        $this->responseProcessor->process($httpFoundationResponse, $response);

        if (!$this->kernel instanceof TerminableInterface) {
            return;
        }

        $this->kernel->terminate($httpFoundationRequest, $httpFoundationResponse);
    }
}

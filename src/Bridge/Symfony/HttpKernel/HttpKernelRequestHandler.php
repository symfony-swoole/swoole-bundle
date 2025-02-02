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
use Symfony\Component\HttpKernel\TerminableInterface;

/**
 * @phpstan-import-type RuntimeConfiguration from Bootable
 */
final readonly class HttpKernelRequestHandler implements RequestHandler, Bootable
{
    public function __construct(
        private KernelPool $kernelPool,
        private RequestFactory $requestFactory,
        private ResponseProcessorInjector $processorInjector,
        private ResponseProcessor $responseProcessor,
    ) {}

    /**
     * @param RuntimeConfiguration $runtimeConfiguration
     */
    public function boot(array $runtimeConfiguration = []): void
    {
        $this->kernelPool->boot();
    }

    /**
     * @throws Exception
     */
    public function handle(SwooleRequest $request, SwooleResponse $response): void
    {
        $httpFoundationRequest = $this->requestFactory->make($request);
        $this->processorInjector->injectProcessor($httpFoundationRequest, $response);
        $kernel = $this->kernelPool->get();
        $httpFoundationResponse = $kernel->handle($httpFoundationRequest);
        $this->responseProcessor->process($httpFoundationResponse, $response);

        if ($kernel instanceof TerminableInterface) {
            $kernel->terminate($httpFoundationRequest, $httpFoundationResponse);
        }

        $this->kernelPool->return($kernel);
    }
}

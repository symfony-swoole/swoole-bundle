<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\HttpKernel;

use Swoole\Http\Request as SwooleRequest;
use Swoole\Http\Response as SwooleResponse;
use SwooleBundle\SwooleBundle\Bridge\Symfony\HttpFoundation\RequestFactoryInterface;
use SwooleBundle\SwooleBundle\Bridge\Symfony\HttpFoundation\ResponseProcessorInjectorInterface;
use SwooleBundle\SwooleBundle\Bridge\Symfony\HttpFoundation\ResponseProcessorInterface;
use SwooleBundle\SwooleBundle\Server\RequestHandler\RequestHandlerInterface;
use SwooleBundle\SwooleBundle\Server\Runtime\BootableInterface;
use Symfony\Component\HttpKernel\TerminableInterface;

final class HttpKernelRequestHandler implements RequestHandlerInterface, BootableInterface
{
    public function __construct(
        private readonly KernelPoolInterface $kernelPool,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly ResponseProcessorInjectorInterface $processorInjector,
        private readonly ResponseProcessorInterface $responseProcessor
    ) {
    }

    public function boot(array $runtimeConfiguration = []): void
    {
        $this->kernelPool->boot();
    }

    /**
     * @throws \Exception
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

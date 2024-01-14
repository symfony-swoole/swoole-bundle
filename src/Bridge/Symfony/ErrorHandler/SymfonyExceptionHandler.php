<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\ErrorHandler;

use Swoole\Http\Request;
use Swoole\Http\Response;
use SwooleBundle\SwooleBundle\Bridge\Symfony\HttpFoundation\RequestFactoryInterface;
use SwooleBundle\SwooleBundle\Bridge\Symfony\HttpFoundation\ResponseProcessorInterface;
use SwooleBundle\SwooleBundle\Server\RequestHandler\ExceptionHandler\ExceptionHandlerInterface;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\TerminableInterface;

final class SymfonyExceptionHandler implements ExceptionHandlerInterface
{
    public function __construct(
        private readonly HttpKernelInterface $kernel,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly ResponseProcessorInterface $responseProcessor,
        private readonly ErrorResponder $errorResponder
    ) {
    }

    public function handle(Request $request, \Throwable $exception, Response $response): void
    {
        $httpFoundationRequest = $this->requestFactory->make($request);
        $httpFoundationResponse = $this->errorResponder->processErroredRequest($httpFoundationRequest, $exception);
        $this->responseProcessor->process($httpFoundationResponse, $response);

        if ($this->kernel instanceof TerminableInterface) {
            $this->kernel->terminate($httpFoundationRequest, $httpFoundationResponse);
        }
    }
}

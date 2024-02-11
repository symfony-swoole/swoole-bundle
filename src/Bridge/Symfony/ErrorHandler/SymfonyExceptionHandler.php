<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\ErrorHandler;

use Swoole\Http\Request;
use Swoole\Http\Response;
use SwooleBundle\SwooleBundle\Bridge\Symfony\HttpFoundation\RequestFactory;
use SwooleBundle\SwooleBundle\Bridge\Symfony\HttpFoundation\ResponseProcessor;
use SwooleBundle\SwooleBundle\Server\RequestHandler\ExceptionHandler\ExceptionHandler;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\TerminableInterface;
use Throwable;

final class SymfonyExceptionHandler implements ExceptionHandler
{
    public function __construct(
        private readonly HttpKernelInterface $kernel,
        private readonly RequestFactory $requestFactory,
        private readonly ResponseProcessor $responseProcessor,
        private readonly ErrorResponder $errorResponder,
    ) {}

    public function handle(Request $request, Throwable $exception, Response $response): void
    {
        $httpFoundationRequest = $this->requestFactory->make($request);
        $httpFoundationResponse = $this->errorResponder->processErroredRequest($httpFoundationRequest, $exception);
        $this->responseProcessor->process($httpFoundationResponse, $response);

        if (!($this->kernel instanceof TerminableInterface)) {
            return;
        }

        $this->kernel->terminate($httpFoundationRequest, $httpFoundationResponse);
    }
}

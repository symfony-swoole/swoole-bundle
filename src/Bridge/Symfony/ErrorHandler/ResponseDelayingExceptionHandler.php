<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\ErrorHandler;

use Assert\Assertion;
use ReflectionMethod;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Throwable;

final class ResponseDelayingExceptionHandler
{
    private ?Response $response = null;

    public function __construct(
        private readonly HttpKernelInterface $kernel,
        private readonly Request $request,
        private readonly ReflectionMethod $throwableHandler,
    ) {}

    public function __invoke(Throwable $e): void
    {
        $response = $this->throwableHandler->invoke(
            $this->kernel,
            $e,
            $this->request,
            HttpKernelInterface::MAIN_REQUEST
        );
        Assertion::isInstanceOf($response, Response::class);
        $this->response = $response;
    }

    public function getResponse(): ?Response
    {
        return $this->response;
    }
}

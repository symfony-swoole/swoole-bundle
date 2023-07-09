<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Symfony\ErrorHandler;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class ExceptionHandlerFactory
{
    public function __construct(
        private HttpKernelInterface $kernel,
        private \ReflectionMethod $throwableHandler
    ) {
    }

    public function newExceptionHandler(Request $request): ResponseDelayingExceptionHandler
    {
        return new ResponseDelayingExceptionHandler(
            $this->kernel,
            $request,
            $this->throwableHandler
        );
    }
}

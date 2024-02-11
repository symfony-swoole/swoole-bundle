<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\ErrorHandler;

use Symfony\Component\ErrorHandler\ErrorHandler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class ErrorResponder
{
    public function __construct(
        private readonly ErrorHandler $errorHandler,
        private readonly ExceptionHandlerFactory $handlerFactory,
    ) {}

    public function processErroredRequest(Request $request, Throwable $throwable): Response
    {
        $exceptionHandler = $this->handlerFactory->newExceptionHandler($request);
        $this->errorHandler->setExceptionHandler($exceptionHandler);
        $this->errorHandler->handleException($throwable);

        return $exceptionHandler->getResponse();
    }
}

<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\ErrorHandler;

use Assert\Assertion;
use Symfony\Component\ErrorHandler\ErrorHandler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final readonly class ErrorResponder
{
    public function __construct(
        private ErrorHandler $errorHandler,
        private ExceptionHandlerFactory $handlerFactory,
    ) {}

    public function processErroredRequest(Request $request, Throwable $throwable): Response
    {
        $exceptionHandler = $this->handlerFactory->newExceptionHandler($request);
        $this->errorHandler->setExceptionHandler($exceptionHandler);
        $this->errorHandler->handleException($throwable);
        $toReturn = $exceptionHandler->getResponse();
        Assertion::isInstanceOf($toReturn, Response::class);

        return $toReturn;
    }
}

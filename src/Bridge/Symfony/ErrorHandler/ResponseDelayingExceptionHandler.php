<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Symfony\ErrorHandler;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class ResponseDelayingExceptionHandler
{
    private ?Response $response = null;

    public function __construct(
        private HttpKernelInterface $kernel,
        private Request $request,
        private \ReflectionMethod $throwableHandler,
    ) {
    }

    public function __invoke(\Throwable $e): void
    {
        $this->response = $this->throwableHandler->invoke(
            $this->kernel,
            $e,
            $this->request,
            HttpKernelInterface::MAIN_REQUEST
        );
    }

    public function getResponse(): ?Response
    {
        return $this->response;
    }
}

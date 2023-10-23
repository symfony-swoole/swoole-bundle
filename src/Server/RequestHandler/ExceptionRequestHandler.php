<?php

declare(strict_types=1);

namespace K911\Swoole\Server\RequestHandler;

use K911\Swoole\Server\RequestHandler\ExceptionHandler\ExceptionHandlerInterface;
use Swoole\Http\Request;
use Swoole\Http\Response;

final class ExceptionRequestHandler implements RequestHandlerInterface
{
    public function __construct(
        private RequestHandlerInterface $decorated,
        private ExceptionHandlerInterface $exceptionHandler
    ) {
    }

    public function handle(Request $request, Response $response): void
    {
        try {
            $this->decorated->handle($request, $response);
        } catch (\Throwable $exception) {
            $this->exceptionHandler->handle($request, $exception, $response);
        }
    }
}

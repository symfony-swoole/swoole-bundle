<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\RequestHandler;

use Swoole\Http\Request;
use Swoole\Http\Response;
use SwooleBundle\SwooleBundle\Server\RequestHandler\ExceptionHandler\ExceptionHandlerInterface;

final class ExceptionRequestHandler implements RequestHandlerInterface
{
    public function __construct(
        private readonly RequestHandlerInterface $decorated,
        private readonly ExceptionHandlerInterface $exceptionHandler
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

<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\RequestHandler;

use Swoole\Http\Request;
use Swoole\Http\Response;
use SwooleBundle\SwooleBundle\Server\RequestHandler\ExceptionHandler\ExceptionHandler;
use Throwable;

final class ExceptionRequestHandler implements RequestHandler
{
    public function __construct(
        private readonly RequestHandler $decorated,
        private readonly ExceptionHandler $exceptionHandler,
    ) {}

    public function handle(Request $request, Response $response): void
    {
        try {
            $this->decorated->handle($request, $response);
        } catch (Throwable $exception) {
            $this->exceptionHandler->handle($request, $exception, $response);
        }
    }
}

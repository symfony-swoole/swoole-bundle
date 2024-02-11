<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\RequestHandler\ExceptionHandler;

use Swoole\Http\Request;
use Swoole\Http\Response;
use Throwable;

interface ExceptionHandler
{
    public function handle(Request $request, Throwable $exception, Response $response): void;
}

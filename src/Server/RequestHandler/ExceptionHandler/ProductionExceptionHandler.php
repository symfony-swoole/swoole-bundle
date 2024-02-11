<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\RequestHandler\ExceptionHandler;

use Swoole\Http\Request;
use Swoole\Http\Response;
use SwooleBundle\SwooleBundle\Client\Http;
use Throwable;

final class ProductionExceptionHandler implements ExceptionHandler
{
    public const ERROR_MESSAGE = 'An unexpected fatal error has occurred. '
        . 'Please report this incident to the administrator of this service.';

    public function handle(Request $request, Throwable $exception, Response $response): void
    {
        $response->header(Http::HEADER_CONTENT_TYPE->value, Http::CONTENT_TYPE_TEXT_PLAIN->value);
        $response->status(500);
        $response->end(self::ERROR_MESSAGE);
    }
}

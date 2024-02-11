<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\ExceptionHandler;

use Swoole\Http\Request;
use Swoole\Http\Response;
use SwooleBundle\SwooleBundle\Client\Http;
use SwooleBundle\SwooleBundle\Server\RequestHandler\ExceptionHandler\ExceptionHandler;
use Throwable;

final class TestCustomExceptionHandler implements ExceptionHandler
{
    public function handle(Request $request, Throwable $exception, Response $response): void
    {
        $response->header(Http::HEADER_CONTENT_TYPE->value, Http::CONTENT_TYPE_TEXT_PLAIN->value);
        $response->status(500);
        $response->end('Very custom exception handler');
    }
}

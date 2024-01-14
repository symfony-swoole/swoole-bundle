<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\ExceptionHandler;

use Swoole\Http\Request;
use Swoole\Http\Response;
use SwooleBundle\SwooleBundle\Client\Http;
use SwooleBundle\SwooleBundle\Server\RequestHandler\ExceptionHandler\ExceptionHandlerInterface;

final class TestCustomExceptionHandler implements ExceptionHandlerInterface
{
    public function handle(Request $request, \Throwable $exception, Response $response): void
    {
        $response->header(Http::HEADER_CONTENT_TYPE, Http::CONTENT_TYPE_TEXT_PLAIN);
        $response->status(500);
        $response->end('Very custom exception handler');
    }
}

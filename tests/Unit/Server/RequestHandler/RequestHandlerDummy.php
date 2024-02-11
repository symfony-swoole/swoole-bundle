<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Server\RequestHandler;

use Swoole\Http\Request;
use Swoole\Http\Response;
use SwooleBundle\SwooleBundle\Server\RequestHandler\RequestHandler;

final class RequestHandlerDummy implements RequestHandler
{
    public function handle(Request $request, Response $response): void {}
}

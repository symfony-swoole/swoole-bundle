<?php

declare(strict_types=1);

namespace K911\Swoole\Server\Middleware;

interface MiddlewareFactory
{
    public function createMiddleware(callable $nextMiddleware): callable;
}

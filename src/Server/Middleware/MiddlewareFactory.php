<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Middleware;

interface MiddlewareFactory
{
    public function createMiddleware(callable $nextMiddleware): callable;
}

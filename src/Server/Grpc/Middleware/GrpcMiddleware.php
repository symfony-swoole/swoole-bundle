<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Grpc\Middleware;

use Swoole\Http\Request;
use Swoole\Http\Response;

/**
 * Interface for gRPC-specific middleware.
 *
 * Similar to HTTP middleware but specifically designed for gRPC requests.
 */
interface GrpcMiddleware
{
    /**
     * Process a gRPC request.
     *
     * @param Request $request The Swoole HTTP request
     * @param Response $response The Swoole HTTP response
     * @param callable $next The next middleware or handler
     */
    public function process(Request $request, Response $response, callable $next): void;
}

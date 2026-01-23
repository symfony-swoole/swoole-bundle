<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Grpc\Factory;

use SwooleBundle\SwooleBundle\Server\Grpc\Context\Context;
use SwooleBundle\SwooleBundle\Server\Grpc\Context\Request;
use SwooleBundle\SwooleBundle\Server\Grpc\Context\Response;
use SwooleBundle\SwooleBundle\Server\HttpServer;

/**
 * Factory for creating gRPC Context instances.
 */
final class ContextFactory
{
    /**
     * Create a new Context instance.
     *
     * @param HttpServer $server the gRPC server instance
     * @param \Swoole\HTTP\Request $swooleRequest the Swoole HTTP request object
     * @param \Swoole\Http\Response $swooleResponse the Swoole HTTP response object
     */
    public function createContext(
        HttpServer $server,
        \Swoole\HTTP\Request $swooleRequest,
        \Swoole\Http\Response $swooleResponse,
    ): Context {
        return new Context(
            server: $server,
            request: new Request($swooleRequest),
            response: new Response($swooleResponse),
        );
    }
}

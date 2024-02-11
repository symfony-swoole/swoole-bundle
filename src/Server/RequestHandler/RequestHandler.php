<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\RequestHandler;

use Swoole\Http\Request;
use Swoole\Http\Response;

interface RequestHandler
{
    /**
     * Handles swoole request and modifies swoole response accordingly.
     */
    public function handle(Request $request, Response $response): void;
}

<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Configurator;

use Swoole\Http\Server;
use SwooleBundle\SwooleBundle\Server\RequestHandler\RequestHandlerInterface;

final class WithRequestHandler implements ConfiguratorInterface
{
    public function __construct(private readonly RequestHandlerInterface $requestHandler)
    {
    }

    public function configure(Server $server): void
    {
        $server->on('request', [$this->requestHandler, 'handle']);
    }
}

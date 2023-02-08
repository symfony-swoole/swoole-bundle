<?php

declare(strict_types=1);

namespace K911\Swoole\Server\Configurator;

use K911\Swoole\Server\RequestHandler\RequestHandlerInterface;
use Swoole\Http\Server;

final class WithRequestHandler implements ConfiguratorInterface
{
    public function __construct(private RequestHandlerInterface $requestHandler)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function configure(Server $server): void
    {
        $server->on('request', [$this->requestHandler, 'handle']);
    }
}

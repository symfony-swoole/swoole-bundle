<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Api;

use Swoole\Http\Server;
use SwooleBundle\SwooleBundle\Server\Config\Sockets;
use SwooleBundle\SwooleBundle\Server\Configurator\Configurator;
use SwooleBundle\SwooleBundle\Server\RequestHandler\RequestHandler;

/**
 * @internal This class will be dropped, once named server listeners will be implemented
 */
final readonly class WithApiServerConfiguration implements Configurator
{
    public function __construct(
        private Sockets $sockets,
        private RequestHandler $requestHandler,
    ) {}

    public function configure(Server $server): void
    {
        if (!$this->sockets->hasApiSocket()) {
            return;
        }

        $apiSocketPort = $this->sockets->getApiSocket()->port();
        foreach ($server->ports as $port) {
            if ($port->port === $apiSocketPort) {
                $port->on('request', $this->requestHandler->handle(...));

                return;
            }
        }
    }
}

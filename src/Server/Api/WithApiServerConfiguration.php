<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Api;

use Swoole\Http\Server;
use SwooleBundle\SwooleBundle\Server\Config\Sockets;
use SwooleBundle\SwooleBundle\Server\Configurator\ConfiguratorInterface;
use SwooleBundle\SwooleBundle\Server\RequestHandler\RequestHandlerInterface;

/**
 * @internal This class will be dropped, once named server listeners will be implemented
 */
final class WithApiServerConfiguration implements ConfiguratorInterface
{
    public function __construct(
        private readonly Sockets $sockets,
        private readonly RequestHandlerInterface $requestHandler
    ) {
    }

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

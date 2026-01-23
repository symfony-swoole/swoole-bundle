<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Grpc;

use Swoole\Http\Server;
use SwooleBundle\SwooleBundle\Server\Config\Sockets;
use SwooleBundle\SwooleBundle\Server\Configurator\Configurator;
use SwooleBundle\SwooleBundle\Server\RequestHandler\RequestHandler;

/**
 * @internal This class will be dropped, once named server listeners will be implemented
 */
final readonly class WithGrpcServerConfiguration implements Configurator
{
    public function __construct(
        private Sockets $sockets,
        private RequestHandler $requestHandler,
    ) {}

    public function configure(Server $server): void
    {
        if (!$this->sockets->hasGrpcSocket()) {
            return;
        }

        $grpcSocketPort = $this->sockets->getGrpcSocket()->port();
        foreach ($server->ports as $port) {
            if ($port->port === $grpcSocketPort) {
                $port->on('request', $this->requestHandler->handle(...));

                return;
            }
        }
    }
}

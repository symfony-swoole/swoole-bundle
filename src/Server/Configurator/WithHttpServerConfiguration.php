<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Configurator;

use Co;
use Swoole\Http\Server;
use SwooleBundle\SwooleBundle\Server\HttpServerConfiguration;

final class WithHttpServerConfiguration implements Configurator
{
    public function __construct(private readonly HttpServerConfiguration $configuration) {}

    public function configure(Server $server): void
    {
        $server->set($this->configuration->getSwooleSettings());

        $defaultSocket = $this->configuration->getServerSocket();
        if ($defaultSocket->port() === 0) {
            $this->configuration->changeServerSocket($defaultSocket->withPort($server->port));
        }

        $maxConcurrency = $this->configuration->getMaxConcurrency();

        if ($maxConcurrency === null) {
            return;
        }

        Co::set(['max_concurrency' => $maxConcurrency]);
    }
}

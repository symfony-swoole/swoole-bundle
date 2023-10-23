<?php

declare(strict_types=1);

namespace K911\Swoole\Server\Configurator;

use K911\Swoole\Server\HttpServerConfiguration;
use Swoole\Http\Server;

final class WithHttpServerConfiguration implements ConfiguratorInterface
{
    public function __construct(private HttpServerConfiguration $configuration)
    {
    }

    public function configure(Server $server): void
    {
        $server->set($this->configuration->getSwooleSettings());

        $defaultSocket = $this->configuration->getServerSocket();
        if (0 === $defaultSocket->port()) {
            $this->configuration->changeServerSocket($defaultSocket->withPort($server->port));
        }

        $maxConcurrency = $this->configuration->getMaxConcurrency();

        if (null === $maxConcurrency) {
            return;
        }

        \Co::set(['max_concurrency' => $maxConcurrency]);
    }
}

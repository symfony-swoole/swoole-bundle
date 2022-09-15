<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Symfony\Event;

use Swoole\Server;
use Symfony\Contracts\EventDispatcher\Event;

final class ServerStartedEvent extends Event
{
    public const NAME = 'swoole_bundle.server.started';

    private Server $server;

    public function __construct(Server $server)
    {
        $this->server = $server;
    }

    public function getServer(): Server
    {
        return $this->server;
    }
}

<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Event;

use Swoole\Server;
use Symfony\Contracts\EventDispatcher\Event;

final class ServerStartedEvent extends Event
{
    public const NAME = 'swoole_bundle.server.started';

    public function __construct(private readonly Server $server)
    {
    }

    public function getServer(): Server
    {
        return $this->server;
    }
}

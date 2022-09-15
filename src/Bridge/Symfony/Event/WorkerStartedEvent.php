<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Symfony\Event;

use Swoole\Server;
use Symfony\Contracts\EventDispatcher\Event;

final class WorkerStartedEvent extends Event
{
    public const NAME = 'swoole_bundle.worker.started';

    private Server $server;

    private int $workerId;

    public function __construct(Server $server, int $workerId)
    {
        $this->server = $server;
        $this->workerId = $workerId;
    }

    public function getServer(): Server
    {
        return $this->server;
    }

    public function getWorkerId(): int
    {
        return $this->workerId;
    }
}

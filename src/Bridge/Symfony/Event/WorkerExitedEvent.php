<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Symfony\Event;

use Swoole\Server;
use Symfony\Contracts\EventDispatcher\Event;

final class WorkerExitedEvent extends Event
{
    public const NAME = 'swoole_bundle.worker.exited';

    public function __construct(
        private Server $server,
        private int $workerId
    ) {
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

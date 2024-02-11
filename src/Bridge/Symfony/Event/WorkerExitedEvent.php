<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Event;

use Swoole\Server;
use Symfony\Contracts\EventDispatcher\Event;

final class WorkerExitedEvent extends Event
{
    public const NAME = 'swoole_bundle.worker.exited';

    public function __construct(
        private readonly Server $server,
        private readonly int $workerId,
    ) {}

    public function getServer(): Server
    {
        return $this->server;
    }

    public function getWorkerId(): int
    {
        return $this->workerId;
    }
}

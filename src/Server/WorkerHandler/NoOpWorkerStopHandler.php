<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\WorkerHandler;

use Swoole\Server;

final class NoOpWorkerStopHandler implements WorkerStopHandler
{
    public function handle(Server $worker, int $workerId): void
    {
        // noop
    }
}

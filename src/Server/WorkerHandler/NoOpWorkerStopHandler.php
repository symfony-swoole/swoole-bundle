<?php

declare(strict_types=1);

namespace K911\Swoole\Server\WorkerHandler;

use Swoole\Server;

final class NoOpWorkerStopHandler implements WorkerStopHandlerInterface
{
    /**
     * {@inheritdoc}
     */
    public function handle(Server $worker, int $workerId): void
    {
        // noop
    }
}

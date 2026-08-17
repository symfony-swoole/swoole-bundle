<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\TaskWorker;

use Override;
use Swoole\Server;

/**
 * EXPERIMENTAL. Recycles a worker through the running swoole server.
 *
 * Built per onWorkerStart rather than injected, because the Server only exists once the worker is up.
 */
final readonly class SwooleWorkerControl implements WorkerControl
{
    public function __construct(private Server $server) {}

    #[Override]
    public function stop(int $workerId): void
    {
        $this->server->stop($workerId);
    }
}

<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Symfony\TaskWorker;

use Override;
use Swoole\Server;
use SwooleBundle\SwooleBundle\Server\WorkerHandler\WorkerStartHandler;

final class RecordingWorkerStartHandler implements WorkerStartHandler
{
    /**
     * @var list<int>
     */
    private array $handledWorkerIds = [];

    /**
     * @return list<int>
     */
    public function handledWorkerIds(): array
    {
        return $this->handledWorkerIds;
    }

    #[Override]
    public function handle(Server $server, int $workerId): void
    {
        $this->handledWorkerIds[] = $workerId;
    }
}

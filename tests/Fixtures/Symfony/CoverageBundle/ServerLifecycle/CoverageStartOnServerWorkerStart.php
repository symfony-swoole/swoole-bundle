<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\CoverageBundle\ServerLifecycle;

use Swoole\Server;
use SwooleBundle\SwooleBundle\Server\WorkerHandler\WorkerStartHandler;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\CoverageBundle\Coverage\CodeCoverageManager;

final class CoverageStartOnServerWorkerStart implements WorkerStartHandler
{
    public function __construct(
        private readonly CodeCoverageManager $codeCoverageManager,
        private readonly ?WorkerStartHandler $decorated = null,
    ) {}

    public function handle(Server $worker, int $workerId): void
    {
        $this->codeCoverageManager->start(sprintf('test_worker_%d', $workerId));

        if (!($this->decorated instanceof WorkerStartHandler)) {
            return;
        }

        $this->decorated->handle($worker, $workerId);
    }
}

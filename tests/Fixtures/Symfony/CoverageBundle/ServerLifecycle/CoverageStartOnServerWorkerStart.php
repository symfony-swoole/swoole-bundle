<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\CoverageBundle\ServerLifecycle;

use Swoole\Server;
use SwooleBundle\SwooleBundle\Server\WorkerHandler\WorkerStartHandlerInterface;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\CoverageBundle\Coverage\CodeCoverageManager;

final class CoverageStartOnServerWorkerStart implements WorkerStartHandlerInterface
{
    public function __construct(
        private readonly CodeCoverageManager $codeCoverageManager,
        private readonly ?WorkerStartHandlerInterface $decorated = null
    ) {
    }

    public function handle(Server $worker, int $workerId): void
    {
        $this->codeCoverageManager->start(sprintf('test_worker_%d', $workerId));

        if ($this->decorated instanceof WorkerStartHandlerInterface) {
            $this->decorated->handle($worker, $workerId);
        }
    }
}

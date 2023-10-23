<?php

declare(strict_types=1);

namespace K911\Swoole\Tests\Fixtures\Symfony\CoverageBundle\ServerLifecycle;

use K911\Swoole\Server\WorkerHandler\WorkerStartHandlerInterface;
use K911\Swoole\Tests\Fixtures\Symfony\CoverageBundle\Coverage\CodeCoverageManager;
use Swoole\Server;

final class CoverageStartOnServerWorkerStart implements WorkerStartHandlerInterface
{
    public function __construct(
        private CodeCoverageManager $codeCoverageManager,
        private ?WorkerStartHandlerInterface $decorated = null
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

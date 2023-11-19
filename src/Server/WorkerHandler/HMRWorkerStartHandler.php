<?php

declare(strict_types=1);

namespace K911\Swoole\Server\WorkerHandler;

use K911\Swoole\Common\SwooleFacade;
use K911\Swoole\Server\Runtime\HMR\HotModuleReloaderInterface;
use Swoole\Server;

final class HMRWorkerStartHandler implements WorkerStartHandlerInterface
{
    public function __construct(
        private HotModuleReloaderInterface $hmr,
        private SwooleFacade $swooleFacade,
        private int $interval = 2000,
        private ?WorkerStartHandlerInterface $decorated = null,
    ) {
    }

    public function handle(Server $worker, int $workerId): void
    {
        if ($this->decorated instanceof WorkerStartHandlerInterface) {
            $this->decorated->handle($worker, $workerId);
        }

        if ($worker->taskworker) {
            return;
        }

        $this->swooleFacade->tick($this->interval, function () use ($worker): void {
            $this->hmr->tick($worker);
        });
    }
}

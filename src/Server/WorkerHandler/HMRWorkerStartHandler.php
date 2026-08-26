<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\WorkerHandler;

use Swoole\Server;
use SwooleBundle\SwooleBundle\Server\Runtime\HMR\HotModuleReloader;
use SwooleBundle\SwooleBundle\Server\Runtime\HMR\HotModuleReloadTimer;

final readonly class HMRWorkerStartHandler implements WorkerStartHandler
{
    public function __construct(
        private HotModuleReloader $hmr,
        private HotModuleReloadTimer $timer,
        private int $interval = 2000,
        private ?WorkerStartHandler $decorated = null,
    ) {}

    public function handle(Server $worker, int $workerId): void
    {
        if ($this->decorated instanceof WorkerStartHandler) {
            $this->decorated->handle($worker, $workerId);
        }

        if ($worker->taskworker) {
            return;
        }

        $this->timer->start($this->interval, function () use ($worker): void {
            $this->hmr->tick($worker);
        });
    }
}

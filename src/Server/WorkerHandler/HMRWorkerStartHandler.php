<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\WorkerHandler;

use Swoole\Server;
use SwooleBundle\SwooleBundle\Common\Adapter\Swoole;
use SwooleBundle\SwooleBundle\Server\Runtime\HMR\HotModuleReloader;

final class HMRWorkerStartHandler implements WorkerStartHandler
{
    public function __construct(
        private readonly HotModuleReloader $hmr,
        private readonly Swoole $swoole,
        private readonly int $interval = 2000,
        private readonly ?WorkerStartHandler $decorated = null,
    ) {}

    public function handle(Server $worker, int $workerId): void
    {
        if ($this->decorated instanceof WorkerStartHandler) {
            $this->decorated->handle($worker, $workerId);
        }

        if ($worker->taskworker) {
            return;
        }

        $this->swoole->tick($this->interval, function () use ($worker): void {
            $this->hmr->tick($worker);
        });
    }
}

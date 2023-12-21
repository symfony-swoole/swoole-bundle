<?php

declare(strict_types=1);

namespace K911\Swoole\Server\WorkerHandler;

use K911\Swoole\Common\Adapter\Swoole;
use K911\Swoole\Server\Runtime\HMR\HotModuleReloaderInterface;
use Swoole\Server;

final class HMRWorkerStartHandler implements WorkerStartHandlerInterface
{
    public function __construct(
        private readonly HotModuleReloaderInterface $hmr,
        private readonly Swoole $swoole,
        private readonly int $interval = 2000,
        private readonly ?WorkerStartHandlerInterface $decorated = null,
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

        $this->swoole->tick($this->interval, function () use ($worker): void {
            $this->hmr->tick($worker);
        });
    }
}

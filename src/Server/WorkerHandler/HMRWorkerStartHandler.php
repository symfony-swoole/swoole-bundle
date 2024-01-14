<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\WorkerHandler;

use Swoole\Server;
use SwooleBundle\SwooleBundle\Common\Adapter\Swoole;
use SwooleBundle\SwooleBundle\Server\Runtime\HMR\HotModuleReloaderInterface;

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

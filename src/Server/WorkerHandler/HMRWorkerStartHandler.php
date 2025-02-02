<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\WorkerHandler;

use Swoole\Server;
use SwooleBundle\SwooleBundle\Common\Adapter\Swoole;
use SwooleBundle\SwooleBundle\Server\Runtime\HMR\HotModuleReloader;

final readonly class HMRWorkerStartHandler implements WorkerStartHandler
{
    public function __construct(
        private HotModuleReloader $hmr,
        private Swoole $swoole,
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

        $this->swoole->tick($this->interval, function () use ($worker): void {
            $this->hmr->tick($worker);
        });
    }
}

<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\WorkerHandler;

use Swoole\Server;
use SwooleBundle\SwooleBundle\Server\Runtime\HMR\HotModuleReloadTimer;

/**
 * Stops HMR's watch timer so the worker holding it can exit when it goes idle.
 *
 * onWorkerExit is the only moment this works. It is raised in the worker while its reactor is still
 * running and still holds the timer, which is precisely the state that keeps the worker from exiting;
 * onWorkerStop, by contrast, is not reached until the process is already on its way out, long after the
 * wait this is meant to avoid has been paid.
 *
 * It fires more than once per shutdown and stopping an already stopped timer does nothing, so no
 * bookkeeping is needed beyond the timer's own.
 *
 * @see HotModuleReloadTimer for what a worker that keeps its timer costs
 */
final readonly class HMRWorkerExitHandler implements WorkerExitHandler
{
    public function __construct(
        private HotModuleReloadTimer $timer,
        private ?WorkerExitHandler $decorated = null,
    ) {}

    public function handle(Server $worker, int $workerId): void
    {
        $this->timer->stop();

        if (!$this->decorated instanceof WorkerExitHandler) {
            return;
        }

        $this->decorated->handle($worker, $workerId);
    }
}

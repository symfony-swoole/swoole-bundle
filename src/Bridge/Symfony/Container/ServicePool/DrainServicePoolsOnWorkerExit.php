<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Container\ServicePool;

use Swoole\Server;
use SwooleBundle\SwooleBundle\Common\Adapter\Swoole;
use SwooleBundle\SwooleBundle\Server\WorkerHandler\WorkerExitHandler;

/**
 * Destroys what the service pools hold for nobody while a worker is on its way out and its reactor still runs,
 * so that what the instances do in their destructors - a mail transport's QUIT, waiting for its 221 - still can.
 *
 * onWorkerExit is the moment: raised while the worker waits for its coroutines to finish, with the reactor still
 * there to run one in. onWorkerStop is too late - the reactor is gone, and an instance destroyed after it is
 * destroyed outside any coroutine, which with the runtime hooks on is a fatal "API must be called in the
 * coroutine" in place of a clean exit. Instances a coroutine still holds are not touched (see
 * ServicePool::drain()); it gives them back when it ends, and the next onWorkerExit - it is raised until the
 * worker is idle - lets go of those.
 */
final readonly class DrainServicePoolsOnWorkerExit implements WorkerExitHandler
{
    public function __construct(
        private ServicePoolContainer $servicePools,
        private Swoole $swoole,
        private ?WorkerExitHandler $decorated = null,
    ) {}

    public function handle(Server $worker, int $workerId): void
    {
        $this->decorated?->handle($worker, $workerId);

        if ($this->swoole->getCoroutineId() !== -1) {
            $this->servicePools->drain();

            return;
        }

        go(function (): void {
            $this->servicePools->drain();
        });
    }
}

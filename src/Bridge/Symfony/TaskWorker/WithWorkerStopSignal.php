<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\TaskWorker;

use Override;
use Swoole\Http\Server;
use SwooleBundle\SwooleBundle\Server\Configurator\Configurator;

/**
 * EXPERIMENTAL. Pins the stop signal's allocation to the master process, and opens its generations.
 *
 * Configurators run in the master, before Server::start() forks anything, so taking WorkerStopSignal as
 * a constructor argument is what forces its Atomics to be allocated there - and only shared memory
 * allocated before the fork is the same memory in every worker.
 *
 * Opening a generation here covers a server started twice in one process, which is how the functional
 * tests run it: the previous run's workers raised the signal for their own generation, and this run's
 * workers are in a later one, so what they left behind reads as meant for them and not for these.
 *
 * The reload hook does the same thing for the workers a reload replaces. It runs in the manager, which
 * inherited this object from the master, and it runs before the reload takes the current workers down -
 * so those workers are still bound to the generation they started in, and the raise their exit triggers
 * lands there rather than on the replacements.
 *
 * @see WithSwooleTableStorageConfigurator for the same pre-fork trick applied to a Swoole\Table
 */
final readonly class WithWorkerStopSignal implements Configurator
{
    public function __construct(private WorkerStopSignal $stopSignal) {}

    #[Override]
    public function configure(Server $server): void
    {
        $this->stopSignal->newGeneration();

        $server->on('BeforeReload', function (): void {
            $this->stopSignal->newGeneration();
        });
    }
}

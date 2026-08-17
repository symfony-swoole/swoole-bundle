<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\TaskWorker;

use Override;
use Swoole\Http\Server;
use SwooleBundle\SwooleBundle\Server\Configurator\Configurator;

/**
 * EXPERIMENTAL. Pins the stop signal's allocation to the master process.
 *
 * Configurators run in the master, before Server::start() forks anything, so taking WorkerStopSignal as
 * a constructor argument is what forces its Atomic to be allocated there - and only shared memory
 * allocated before the fork is the same memory in every worker.
 *
 * The reset covers a server started twice in one process, which is how the functional tests run it: a
 * flag left raised by the previous run would stop the next run's commands the moment they started.
 *
 * @see WithSwooleTableStorageConfigurator for the same pre-fork trick applied to a Swoole\Table
 */
final readonly class WithWorkerStopSignal implements Configurator
{
    public function __construct(private WorkerStopSignal $stopSignal) {}

    #[Override]
    public function configure(Server $server): void
    {
        $this->stopSignal->reset();
    }
}

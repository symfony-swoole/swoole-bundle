<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Xdebug;

use Override;
use Swoole\Server;
use SwooleBundle\SwooleBundle\Server\WorkerHandler\WorkerStartHandler;

/**
 * Attaches a worker to the debugging client as it starts, before it does any work.
 *
 * This is what xdebug.start_with_request=yes was reaching for and could not get right. onWorkerStart
 * runs in the worker, after the fork, so the master is never in a session and never held in one - the
 * failure that leaves a server permanently half-started. It also runs again for every replacement
 * worker, which is what makes a debugger survive a reload or a worker recycled on --memory-limit.
 *
 * Applies to whatever worker is starting, http or task, and that is the point: a task worker is where
 * message handlers, projections and the long running commands live, and none of them is reachable from
 * a request, so nothing a browser sends can ever attach the process running them. For an http worker
 * this is the blunter alternative to the per-request trigger - useful when the code to break in runs
 * during boot, where no request exists yet either.
 *
 * Off by default, because it attaches unconditionally: every worker of every kind opens a connection
 * as it starts, and where no IDE is listening each pays xdebug.connect_timeout_ms to find that out.
 *
 * @see XdebugClient for why attaching has to be done from PHP here
 * @see AttachXdebugTaskHandler for attaching per task instead, which costs nothing until a task arrives
 */
final readonly class AttachXdebugWorkerStartHandler implements WorkerStartHandler
{
    public function __construct(
        private XdebugClient $xdebug,
        private ?WorkerStartHandler $decorated = null,
    ) {}

    #[Override]
    public function handle(Server $worker, int $workerId): void
    {
        // Before the decorated handler rather than after: with coroutines off, a task worker running a
        // long running command never returns from the call below, so anything left until afterwards
        // would never run in that worker at all.
        $this->xdebug->attach();

        $this->decorated?->handle($worker, $workerId);
    }
}

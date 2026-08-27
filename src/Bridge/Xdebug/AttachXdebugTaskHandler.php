<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Xdebug;

use Override;
use Swoole\Server;
use SwooleBundle\SwooleBundle\Server\TaskHandler\TaskHandler;

/**
 * Attaches a task worker to the debugging client when a task arrives, before the task is handled.
 *
 * The counterpart to the request handler, for the half of the application no request reaches. Where
 * the messenger task transport is in use, a task is a message, so this is what makes a breakpoint in a
 * message handler reachable.
 *
 * Cheaper than attaching in onWorkerStart, and for most debugging more useful: an idle task worker
 * never connects, and the connection is made in the worker that is about to run the handler. A task
 * worker that has attached stays attached, so the cost is paid once per worker and not once per task.
 *
 * There is no trigger here, and no way to build one: a task carries the application's own payload, not
 * headers or cookies, so there is nothing to carry a request's intent across. Enabling this attaches
 * on the next task that arrives, whatever it is.
 *
 * @see AttachXdebugWorkerStartHandler for attaching as the worker starts, which also covers boot
 */
final readonly class AttachXdebugTaskHandler implements TaskHandler
{
    public function __construct(
        private TaskHandler $decorated,
        private XdebugClient $xdebug,
    ) {}

    #[Override]
    public function handle(Server $server, Server\Task $task): void
    {
        $this->xdebug->attach();

        $this->decorated->handle($server, $task);
    }
}

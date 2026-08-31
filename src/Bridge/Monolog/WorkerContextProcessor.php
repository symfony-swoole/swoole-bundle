<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Monolog;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use Override;
use SwooleBundle\SwooleBundle\Bridge\Symfony\TaskWorker\RunningCommand;
use SwooleBundle\SwooleBundle\Common\Adapter\Swoole;

/**
 * Says where a log line came from: which worker, which coroutine, and which command.
 *
 * A server writes one log from many processes at once, and inside each of them from many coroutines at
 * once, so the lines of one request or one message arrive interleaved with everybody else's. These three
 * are what put them back together - `worker` narrows a line to a process, `cid` to a single unit of work
 * inside it, and `command` says which of a task worker's commands it belongs to.
 *
 * A separate log file per worker was the alternative, and it answers less: it cannot tell two commands
 * of one task worker apart, cannot follow a single request through the interleaving, and leaves an
 * aggregator with several files to stitch back together. Three fields in `extra` are what a log shipper
 * already knows how to index.
 *
 * Each field is left out rather than written empty when there is nothing to say, so that a line from
 * outside a server does not carry three nulls explaining that it is not from a server.
 */
final readonly class WorkerContextProcessor implements ProcessorInterface
{
    public function __construct(
        private WorkerIdentity $worker,
        private RunningCommand $runningCommand,
        private Swoole $swoole,
    ) {}

    #[Override]
    public function __invoke(LogRecord $record): LogRecord
    {
        $extra = $record->extra;

        $worker = $this->worker->label();

        if ($worker !== null) {
            $extra['worker'] = $worker;
        }

        $cid = $this->swoole->getCoroutineId();

        if ($cid > 0) {
            $extra['cid'] = $cid;
        }

        $commandLine = $this->runningCommand->current();

        if ($commandLine !== null) {
            $extra['command'] = $commandLine;
        }

        return $record->with(extra: $extra);
    }
}

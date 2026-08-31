<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Monolog;

use SwooleBundle\SwooleBundle\Bridge\Symfony\Event\WorkerStartedEvent;

/**
 * Which worker of the server this process is, for a log line to say.
 *
 * Every worker writes to the same log, so without this a line says which server wrote it and nothing
 * more - and with several http workers and a task worker per group of commands, that is most of the
 * question left unanswered.
 *
 * The label is set when the worker starts and never changes: a worker is one worker for as long as it
 * lives, and a replacement forked in its place runs onWorkerStart again and takes the same id.
 */
final class WorkerIdentity
{
    private ?string $label = null;

    /**
     * Swoole numbers the task workers straight on from the http ones - with four http workers the first
     * task worker is worker 4 - so the count of http workers is what turns an id back into "the second
     * task worker", which is how a group of commands is configured.
     *
     * @see \SwooleBundle\SwooleBundle\Bridge\Symfony\TaskWorker\LongRunningCommandsWorkerStartHandler
     *      for the same arithmetic, deciding which group a task worker runs
     */
    public static function labelFor(int $httpWorkerCount, int $workerId): string
    {
        return $workerId >= $httpWorkerCount
            ? sprintf('task-%d', $workerId - $httpWorkerCount)
            : sprintf('web-%d', $workerId);
    }

    public function onWorkerStarted(WorkerStartedEvent $event): void
    {
        // Read from the running server rather than from the bundle's configuration, because it is the
        // number swoole actually forked on, and it is the same one the task worker start handler counts
        // groups from - a label saying task-1 has to mean the second configured group.
        /** @var array{worker_num?: int|string} $settings */
        $settings = $event->getServer()->setting;

        $this->label = self::labelFor((int) ($settings['worker_num'] ?? 1), $event->getWorkerId());
    }

    /**
     * Null in a process that is not a worker of a running server - the master, and the console.
     */
    public function label(): ?string
    {
        return $this->label;
    }
}

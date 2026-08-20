<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Runtime\HMR;

use SwooleBundle\SwooleBundle\Common\Adapter\Swoole;

/**
 * Owns the repeating timer HMR watches files on, for the lifetime of one worker process.
 *
 * It exists to be shared between the handler that starts the timer at onWorkerStart and the one that
 * stops it at onWorkerExit - two services, so the timer id has to live somewhere they can both reach.
 *
 * Stopping it is not tidiness. A worker only exits once its reactor has no events left, and a repeating
 * timer is an event that never runs out, so a worker that still holds one waits out `max_wait_time` and
 * is force-terminated rather than exiting when it goes idle. That is three seconds per worker on
 * swoole's default, and as much as the slowest task worker command needs wherever `worker_max_wait_time`
 * has been raised to accommodate one - paid on every stop and on every reload, which for HMR is every
 * time a file changes.
 *
 * @see HMRWorkerExitHandler for why onWorkerExit is the only moment this can still be done
 */
final class HotModuleReloadTimer
{
    private ?int $timerId = null;

    public function __construct(private readonly Swoole $swoole) {}

    /**
     * @param callable(): void $onTick
     */
    public function start(int $intervalMs, callable $onTick): void
    {
        $timerId = $this->swoole->tick($intervalMs, $onTick);

        // Swoole answers with the timer's id, or false when it could not make one. Only an id can be
        // cleared later, and there is nothing useful to do about a timer that was never created -
        // HMR simply does not run in that worker.
        $this->timerId = is_int($timerId) ? $timerId : null;
    }

    public function stop(): void
    {
        if ($this->timerId === null) {
            return;
        }

        $this->swoole->clearTimer($this->timerId);
        $this->timerId = null;
    }

    public function isRunning(): bool
    {
        return $this->timerId !== null;
    }
}

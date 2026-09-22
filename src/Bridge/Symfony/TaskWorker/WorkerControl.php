<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\TaskWorker;

/**
 * EXPERIMENTAL. What a command group does to the server it runs in: recycle its worker, or end the lot.
 *
 * Behind an interface because Server::stop() is not the same method on both engines - swoole has
 * stop(int $workerId = -1), openswoole has stop(int $workerId, bool $waitEvent = false) - so anything
 * calling it directly is engine-specific by construction, test doubles included.
 */
interface WorkerControl
{
    public function stop(int $workerId): void;

    /**
     * Ends the server, every worker of it, from whichever worker asks.
     *
     * For the one case a recycle cannot answer: commands that end as fast as they start would be forked
     * again into the same thing forever, so the server stops instead and leaves the restarting to
     * whatever supervises the container.
     */
    public function shutdown(): void;
}

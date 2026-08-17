<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\TaskWorker;

/**
 * EXPERIMENTAL. The one thing a command group does to the server it runs in: recycle its worker.
 *
 * Behind an interface because Server::stop() is not the same method on both engines - swoole has
 * stop(int $workerId = -1), openswoole has stop(int $workerId, bool $waitEvent = false) - so anything
 * calling it directly is engine-specific by construction, test doubles included.
 */
interface WorkerControl
{
    public function stop(int $workerId): void;
}

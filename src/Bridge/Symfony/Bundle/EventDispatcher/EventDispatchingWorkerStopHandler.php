<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Symfony\Bundle\EventDispatcher;

use K911\Swoole\Bridge\Symfony\Event\WorkerStoppedEvent;
use K911\Swoole\Server\WorkerHandler\WorkerStopHandlerInterface;
use Swoole\Server;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class EventDispatchingWorkerStopHandler implements WorkerStopHandlerInterface
{
    public function __construct(private EventDispatcherInterface $eventDispatcher)
    {
    }

    public function handle(Server $server, int $workerId): void
    {
        $this->eventDispatcher->dispatch(new WorkerStoppedEvent($server, $workerId), WorkerStoppedEvent::NAME);
    }
}

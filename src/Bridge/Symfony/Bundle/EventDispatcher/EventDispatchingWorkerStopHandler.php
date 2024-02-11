<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\EventDispatcher;

use Swoole\Server;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Event\WorkerStoppedEvent;
use SwooleBundle\SwooleBundle\Server\WorkerHandler\WorkerStopHandler;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class EventDispatchingWorkerStopHandler implements WorkerStopHandler
{
    public function __construct(private readonly EventDispatcherInterface $eventDispatcher) {}

    public function handle(Server $server, int $workerId): void
    {
        $this->eventDispatcher->dispatch(new WorkerStoppedEvent($server, $workerId), WorkerStoppedEvent::NAME);
    }
}

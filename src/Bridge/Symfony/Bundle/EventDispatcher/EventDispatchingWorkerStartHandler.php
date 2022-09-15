<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Symfony\Bundle\EventDispatcher;

use K911\Swoole\Bridge\Symfony\Event\WorkerStartedEvent;
use K911\Swoole\Server\WorkerHandler\WorkerStartHandlerInterface;
use Swoole\Server;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class EventDispatchingWorkerStartHandler implements WorkerStartHandlerInterface
{
    private EventDispatcherInterface $eventDispatcher;

    public function __construct(EventDispatcherInterface $eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    public function handle(Server $server, int $workerId): void
    {
        $this->eventDispatcher->dispatch(new WorkerStartedEvent($server, $workerId), WorkerStartedEvent::NAME);
    }
}

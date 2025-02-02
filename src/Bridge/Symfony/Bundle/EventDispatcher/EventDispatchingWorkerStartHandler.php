<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\EventDispatcher;

use Swoole\Server;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Event\WorkerStartedEvent;
use SwooleBundle\SwooleBundle\Server\WorkerHandler\WorkerStartHandler;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final readonly class EventDispatchingWorkerStartHandler implements WorkerStartHandler
{
    public function __construct(private EventDispatcherInterface $eventDispatcher) {}

    public function handle(Server $server, int $workerId): void
    {
        $this->eventDispatcher->dispatch(new WorkerStartedEvent($server, $workerId), WorkerStartedEvent::NAME);
    }
}

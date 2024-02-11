<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\EventDispatcher;

use Swoole\Server;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Event\WorkerExitedEvent;
use SwooleBundle\SwooleBundle\Server\WorkerHandler\WorkerExitHandler;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class EventDispatchingWorkerExitHandler implements WorkerExitHandler
{
    public function __construct(private readonly EventDispatcherInterface $eventDispatcher) {}

    public function handle(Server $server, int $workerId): void
    {
        $this->eventDispatcher->dispatch(new WorkerExitedEvent($server, $workerId), WorkerExitedEvent::NAME);
    }
}

<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Symfony\Bundle\EventDispatcher;

use K911\Swoole\Bridge\Symfony\Event\WorkerExitedEvent;
use K911\Swoole\Server\WorkerHandler\WorkerExitHandlerInterface;
use Swoole\Server;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class EventDispatchingWorkerExitHandler implements WorkerExitHandlerInterface
{
    public function __construct(private EventDispatcherInterface $eventDispatcher)
    {
    }

    public function handle(Server $server, int $workerId): void
    {
        $this->eventDispatcher->dispatch(new WorkerExitedEvent($server, $workerId), WorkerExitedEvent::NAME);
    }
}

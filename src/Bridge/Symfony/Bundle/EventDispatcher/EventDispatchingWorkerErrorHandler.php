<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Symfony\Bundle\EventDispatcher;

use K911\Swoole\Bridge\Symfony\Event\WorkerErrorEvent;
use K911\Swoole\Server\WorkerHandler\WorkerErrorHandlerInterface;
use Swoole\Server;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class EventDispatchingWorkerErrorHandler implements WorkerErrorHandlerInterface
{
    public function __construct(private EventDispatcherInterface $eventDispatcher)
    {
    }

    public function handle(Server $server, int $workerId): void
    {
        $this->eventDispatcher->dispatch(new WorkerErrorEvent($server, $workerId), WorkerErrorEvent::NAME);
    }
}

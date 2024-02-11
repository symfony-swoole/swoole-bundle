<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\EventDispatcher;

use Swoole\Server;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Event\WorkerErrorEvent;
use SwooleBundle\SwooleBundle\Server\WorkerHandler\WorkerErrorHandler;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class EventDispatchingWorkerErrorHandler implements WorkerErrorHandler
{
    public function __construct(private readonly EventDispatcherInterface $eventDispatcher) {}

    public function handle(Server $server, int $workerId): void
    {
        $this->eventDispatcher->dispatch(new WorkerErrorEvent($server, $workerId), WorkerErrorEvent::NAME);
    }
}

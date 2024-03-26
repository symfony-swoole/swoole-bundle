<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\EventDispatcher;

use Swoole\Server;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Event\WorkerStartedEvent;
use SwooleBundle\SwooleBundle\Component\Locking\Coordinator\Constants;
use SwooleBundle\SwooleBundle\Component\Locking\Coordinator\CoordinatorManager;
use SwooleBundle\SwooleBundle\Server\WorkerHandler\WorkerStartHandler;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class EventDispatchingWorkerStartHandler implements WorkerStartHandler
{
    public function __construct(private readonly EventDispatcherInterface $eventDispatcher) {}

    public function handle(Server $server, int $workerId): void
    {
        $this->eventDispatcher->dispatch(new WorkerStartedEvent($server, $workerId), WorkerStartedEvent::NAME);

        CoordinatorManager::until(Constants::WORKER_START)->resume();
    }
}

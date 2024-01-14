<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\EventHandler;

use SwooleBundle\SwooleBundle\Bridge\Symfony\Event\ServerStartedEvent;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Event\WorkerErrorEvent;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Event\WorkerExitedEvent;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Event\WorkerStartedEvent;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Event\WorkerStoppedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class LifecycleEventsEventHandler implements EventSubscriberInterface
{
    private bool $serverStarted = false;

    private bool $workerStarted = false;

    private bool $workerStopped = false;

    private bool $workerExited = false;

    private bool $workerError = false;

    public function onServerStarted(ServerStartedEvent $event): void
    {
        $this->serverStarted = true;
    }

    public function onWorkerStarted(WorkerStartedEvent $event): void
    {
        $this->workerStarted = true;
    }

    public function onWorkerStopped(WorkerStoppedEvent $event): void
    {
        $this->workerStopped = true;
    }

    public function onWorkerExited(WorkerExitedEvent $event): void
    {
        $this->workerExited = true;
    }

    public function onWorkerError(WorkerErrorEvent $event): void
    {
        $this->workerError = true;
    }

    public function isServerStarted(): bool
    {
        return $this->serverStarted;
    }

    public function isWorkerStarted(): bool
    {
        return $this->workerStarted;
    }

    public function isWorkerStopped(): bool
    {
        return $this->workerStopped;
    }

    public function isWorkerExited(): bool
    {
        return $this->workerExited;
    }

    public function isWorkerError(): bool
    {
        return $this->workerError;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ServerStartedEvent::NAME => 'onServerStarted',
            WorkerStartedEvent::NAME => 'onWorkerStarted',
            WorkerStoppedEvent::NAME => 'onWorkerStopped',
            WorkerExitedEvent::NAME => 'onWorkerExited',
            WorkerErrorEvent::NAME => 'onWorkerError',
        ];
    }
}

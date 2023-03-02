<?php

declare(strict_types=1);

namespace K911\Swoole\Tests\Fixtures\Symfony\TestBundle\EventHandler;

use K911\Swoole\Bridge\Symfony\Event\ServerStartedEvent;
use K911\Swoole\Bridge\Symfony\Event\WorkerErrorEvent;
use K911\Swoole\Bridge\Symfony\Event\WorkerExitedEvent;
use K911\Swoole\Bridge\Symfony\Event\WorkerStartedEvent;
use K911\Swoole\Bridge\Symfony\Event\WorkerStoppedEvent;
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

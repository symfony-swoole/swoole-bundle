<?php

declare(strict_types=1);

namespace K911\Swoole\Tests\Fixtures\Symfony\TestBundle\EventHandler;

use K911\Swoole\Bridge\Symfony\Event\ServerStartedEvent;
use K911\Swoole\Bridge\Symfony\Event\WorkerStartedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class LifecycleEventsEventHandler implements EventSubscriberInterface
{
    private bool $serverStarted = false;

    private bool $workerStarted = false;

    public function onServerStarted(ServerStartedEvent $event): void
    {
        $this->serverStarted = true;
    }

    public function onWorkerStarted(WorkerStartedEvent $event): void
    {
        $this->workerStarted = true;
    }

    public function isServerStarted(): bool
    {
        return $this->serverStarted;
    }

    public function isWorkerStarted(): bool
    {
        return $this->workerStarted;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ServerStartedEvent::NAME => 'onServerStarted',
            WorkerStartedEvent::NAME => 'onWorkerStarted',
        ];
    }
}

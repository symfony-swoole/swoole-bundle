<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Symfony\Bundle\EventDispatcher;

use K911\Swoole\Bridge\Symfony\Event\ServerStartedEvent;
use K911\Swoole\Server\LifecycleHandler\ServerStartHandlerInterface;
use Swoole\Server;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class EventDispatchingServerStartHandler implements ServerStartHandlerInterface
{
    private EventDispatcherInterface $eventDispatcher;

    public function __construct(EventDispatcherInterface $eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    public function handle(Server $server): void
    {
        $this->eventDispatcher->dispatch(new ServerStartedEvent($server), ServerStartedEvent::NAME);
    }
}

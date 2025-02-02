<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\EventDispatcher;

use Swoole\Server;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Event\ServerStartedEvent;
use SwooleBundle\SwooleBundle\Server\LifecycleHandler\ServerStartHandler;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final readonly class EventDispatchingServerStartHandler implements ServerStartHandler
{
    public function __construct(private EventDispatcherInterface $eventDispatcher) {}

    public function handle(Server $server): void
    {
        $this->eventDispatcher->dispatch(new ServerStartedEvent($server), ServerStartedEvent::NAME);
    }
}

<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Symfony\HttpFoundation\Session;

use K911\Swoole\Bridge\Symfony\Event\RequestWithSessionFinishedEvent;
use K911\Swoole\Server\Session\StorageInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Storage\MetadataBag;
use Symfony\Component\HttpFoundation\Session\Storage\SessionStorageFactoryInterface;
use Symfony\Component\HttpFoundation\Session\Storage\SessionStorageInterface;

final class SwooleSessionStorageFactory implements SessionStorageFactoryInterface
{
    public function __construct(
        private StorageInterface $storage,
        private EventDispatcherInterface $dispatcher,
        private ?MetadataBag $metadataBag = null,
        private int $lifetimeSeconds = 86400
    ) {
    }

    public function createStorage(?Request $request): SessionStorageInterface
    {
        $storage = new SwooleSessionStorage(
            $this->storage,
            SwooleSessionStorage::DEFAULT_SESSION_NAME,
            $this->lifetimeSeconds,
            $this->metadataBag
        );

        $this->dispatcher->addListener(
            RequestWithSessionFinishedEvent::NAME,
            function (RequestWithSessionFinishedEvent $event) use ($storage) {
                if ($storage->isStarted() && $event->getSessionId() === $storage->getId()) {
                    $storage->reset();
                }
            }
        );

        return $storage;
    }
}

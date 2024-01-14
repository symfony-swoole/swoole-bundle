<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\HttpFoundation\Session;

use SwooleBundle\SwooleBundle\Bridge\Symfony\Event\RequestWithSessionFinishedEvent;
use SwooleBundle\SwooleBundle\Server\Session\StorageInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Storage\MetadataBag;
use Symfony\Component\HttpFoundation\Session\Storage\SessionStorageFactoryInterface;
use Symfony\Component\HttpFoundation\Session\Storage\SessionStorageInterface;

final class SwooleSessionStorageFactory implements SessionStorageFactoryInterface
{
    public function __construct(
        private readonly StorageInterface $storage,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly ?MetadataBag $metadataBag = null,
        private readonly int $lifetimeSeconds = 86400
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

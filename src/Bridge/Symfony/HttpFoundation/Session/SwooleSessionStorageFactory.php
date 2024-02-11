<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\HttpFoundation\Session;

use SwooleBundle\SwooleBundle\Bridge\Symfony\Event\RequestWithSessionFinishedEvent;
use SwooleBundle\SwooleBundle\Server\Session\Storage;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Storage\MetadataBag;
use Symfony\Component\HttpFoundation\Session\Storage\SessionStorageFactoryInterface;
use Symfony\Component\HttpFoundation\Session\Storage\SessionStorageInterface;

final class SwooleSessionStorageFactory implements SessionStorageFactoryInterface
{
    public function __construct(
        private readonly Storage $storage,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly ?MetadataBag $metadataBag = null,
        private readonly int $lifetimeSeconds = 86400,
    ) {}

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
            static function (RequestWithSessionFinishedEvent $event) use ($storage): void {
                if (!$storage->isStarted() || $event->getSessionId() !== $storage->getId()) {
                    return;
                }

                $storage->reset();
            }
        );

        return $storage;
    }
}

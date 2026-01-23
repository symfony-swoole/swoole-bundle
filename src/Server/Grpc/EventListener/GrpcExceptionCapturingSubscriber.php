<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Grpc\EventListener;

use SwooleBundle\SwooleBundle\Server\Grpc\Enum\ContentType;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class GrpcExceptionCapturingSubscriber implements EventSubscriberInterface
{
    public const ATTRIBUTE_KEY = '_grpc_original_exception';

    public function onKernelException(ExceptionEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        if (!str_starts_with($event->getRequest()->headers->get('content-type') ?? '', ContentType::GRPC->value)) {
            return;
        }

        $event->getRequest()->attributes->set(self::ATTRIBUTE_KEY, $event->getThrowable());
    }

    /**
     * @return array<string, array<int, int|string>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            // Run before any listener that sets a response (e.g. ErrorListener at priority 0)
            // so the original throwable is always captured.
            KernelEvents::EXCEPTION => ['onKernelException', 2048],
        ];
    }
}

<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Grpc\EventListener;

use Google\Protobuf\Internal\Message;
use SwooleBundle\SwooleBundle\Server\Grpc\Enum\ContentType;
use SwooleBundle\SwooleBundle\Server\Grpc\HttpFoundation\GrpcResponse;
use SwooleBundle\SwooleBundle\Server\Grpc\Serialization\PayloadSerializer;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class GrpcMessageViewSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly PayloadSerializer $serializer,
    ) {}

    public function onKernelView(ViewEvent $event): void
    {
        $result = $event->getControllerResult();

        if (!($result instanceof Message)) {
            return;
        }

        $contentType = $event->getRequest()->headers->get('content-type') ?? ContentType::GRPC->value;
        $event->setResponse(new GrpcResponse($result, $this->serializer, $contentType));
    }

    /**
     * @return array<string, array<int, int|string>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::VIEW => ['onKernelView', 10],
        ];
    }
}

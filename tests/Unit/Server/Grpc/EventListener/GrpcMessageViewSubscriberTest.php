<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Server\Grpc\EventListener;

use Google\Protobuf\StringValue;
use PHPUnit\Framework\TestCase;
use stdClass;
use SwooleBundle\SwooleBundle\Server\Grpc\EventListener\GrpcMessageViewSubscriber;
use SwooleBundle\SwooleBundle\Server\Grpc\HttpFoundation\GrpcResponse;
use SwooleBundle\SwooleBundle\Server\Grpc\Serialization\PayloadSerializer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

final class GrpcMessageViewSubscriberTest extends TestCase
{
    private GrpcMessageViewSubscriber $subscriber;
    private PayloadSerializer $serializer;

    protected function setUp(): void
    {
        $this->serializer = $this->createMock(PayloadSerializer::class);
        $this->subscriber = new GrpcMessageViewSubscriber($this->serializer);
    }

    public function testSubscribesToViewEvent(): void
    {
        $events = GrpcMessageViewSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey(KernelEvents::VIEW, $events);
    }

    public function testWrapsProtobufMessageInGrpcResponse(): void
    {
        $message = new StringValue();
        $this->serializer->method('serialize')->willReturn('serialized');
        $event = $this->createViewEvent($message);

        $this->subscriber->onKernelView($event);

        $response = $event->getResponse();
        $this->assertInstanceOf(GrpcResponse::class, $response);
        $this->assertSame('serialized', $response->getContent());
    }

    public function testUsesContentTypeFromRequest(): void
    {
        $message = new StringValue();
        $this->serializer->expects($this->once())
            ->method('serialize')
            ->with($message, 'application/grpc+json')
            ->willReturn('{}');
        $event = $this->createViewEvent($message, 'application/grpc+json');

        $this->subscriber->onKernelView($event);

        $this->assertSame('{}', $event->getResponse()->getContent());
    }

    public function testUsesDefaultContentTypeWhenHeaderMissing(): void
    {
        $message = new StringValue();
        $this->serializer->expects($this->once())
            ->method('serialize')
            ->with($message, 'application/grpc')
            ->willReturn('bytes');
        $event = $this->createViewEvent($message);

        $this->subscriber->onKernelView($event);
    }

    public function testIgnoresNonProtobufControllerResult(): void
    {
        $event = $this->createViewEvent(new stdClass());

        $this->subscriber->onKernelView($event);

        $this->assertNull($event->getResponse());
    }

    public function testIgnoresStringControllerResult(): void
    {
        $event = $this->createViewEvent('some string result');

        $this->subscriber->onKernelView($event);

        $this->assertNull($event->getResponse());
    }

    private function createViewEvent(mixed $controllerResult, ?string $contentType = null): ViewEvent
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('/test');

        if ($contentType !== null) {
            $request->headers->set('content-type', $contentType);
        }

        return new ViewEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $controllerResult);
    }
}

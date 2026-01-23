<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Server\Grpc\HttpFoundation;

use Google\Protobuf\StringValue;
use PHPUnit\Framework\TestCase;
use SwooleBundle\SwooleBundle\Server\Grpc\HttpFoundation\GrpcResponse;
use SwooleBundle\SwooleBundle\Server\Grpc\Serialization\PayloadSerializer;
use Symfony\Component\HttpFoundation\Response;

final class GrpcResponseTest extends TestCase
{
    private PayloadSerializer $serializer;

    protected function setUp(): void
    {
        $this->serializer = $this->createMock(PayloadSerializer::class);
    }

    public function testResponseHasHttpOkStatus(): void
    {
        $message = new StringValue();
        $this->serializer->method('serialize')->willReturn('');
        $response = new GrpcResponse($message, $this->serializer, 'application/grpc');

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testGetContentReturnsSerializedPayload(): void
    {
        $message = new StringValue();
        $this->serializer->method('serialize')->with($message, 'application/grpc')->willReturn('serialized-bytes');
        $response = new GrpcResponse($message, $this->serializer, 'application/grpc');

        $this->assertSame('serialized-bytes', $response->getContent());
    }

    public function testSerializerIsCalledWithCorrectContentType(): void
    {
        $message = new StringValue();
        $this->serializer->expects($this->once())
            ->method('serialize')
            ->with($message, 'application/grpc+json')
            ->willReturn('{}');
        $response = new GrpcResponse($message, $this->serializer, 'application/grpc+json');

        $this->assertSame('{}', $response->getContent());
    }

    public function testResponseExtendsSymfonyResponse(): void
    {
        $message = new StringValue();
        $this->serializer->method('serialize')->willReturn('');
        $response = new GrpcResponse($message, $this->serializer, 'application/grpc');

        $this->assertInstanceOf(Response::class, $response);
    }

    public function testCustomHeadersAreSet(): void
    {
        $message = new StringValue();
        $this->serializer->method('serialize')->willReturn('');
        $response = new GrpcResponse($message, $this->serializer, 'application/grpc', ['x-custom' => 'value']);

        $this->assertEquals('value', $response->headers->get('x-custom'));
    }
}

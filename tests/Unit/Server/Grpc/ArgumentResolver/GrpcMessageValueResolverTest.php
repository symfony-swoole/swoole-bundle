<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Server\Grpc\ArgumentResolver;

use Google\Protobuf\Internal\Message;
use Google\Protobuf\StringValue;
use PHPUnit\Framework\TestCase;
use stdClass;
use SwooleBundle\SwooleBundle\Server\Grpc\ArgumentResolver\GrpcMessageValueResolver;
use SwooleBundle\SwooleBundle\Server\Grpc\Serialization\PayloadDeserializer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

final class GrpcMessageValueResolverTest extends TestCase
{
    private PayloadDeserializer $serializer;
    private GrpcMessageValueResolver $resolver;

    protected function setUp(): void
    {
        $this->serializer = $this->createMock(PayloadDeserializer::class);
        $this->resolver = new GrpcMessageValueResolver($this->serializer);
    }

    public function testYieldsNothingWhenTypeIsNotAMessageOrNUll(): void
    {
        $result = $this->resolver->resolve(
            Request::create('/'),
            $this->makeArgument(stdClass::class)
        );

        $this->assertEmpty(iterator_to_array($result));

        $result = $this->resolver->resolve(
            Request::create('/'),
            $this->makeArgument(null)
        );

        $this->assertEmpty(iterator_to_array($result));

        // Message itself must not be resolved — it must not be instantiated directly
        $result = $this->resolver->resolve(
            Request::create('/'),
            $this->makeArgument(Message::class)
        );

        $this->assertEmpty(iterator_to_array($result));
    }

    public function testYieldsDeserializedMessageFromStringContent(): void
    {
        $message = new StringValue();
        $message->setValue('deserialized');

        $request = Request::create('/', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/grpc'], 'raw-binary');

        $this->serializer->expects($this->once())
            ->method('deserialize')
            ->with('raw-binary', StringValue::class, 'application/grpc')
            ->willReturn($message);

        $result = $this->resolver->resolve(
            $request,
            $this->makeArgument(StringValue::class)
        );


        $resolved = iterator_to_array($result);
        $this->assertCount(1, $resolved);
        $this->assertSame($message, $resolved[0]);
    }

    private function makeArgument(?string $type): ArgumentMetadata
    {
        return new ArgumentMetadata('message', $type, false, false, null);
    }
}

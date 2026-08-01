<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Server\Grpc\Serialization;

use Google\Protobuf\StringValue;
use PHPUnit\Framework\TestCase;
use SwooleBundle\SwooleBundle\Server\Grpc\Serialization\ProtobufSerializerDeserializer;

final class ProtobufSerializerDeserializerTest extends TestCase
{
    private ProtobufSerializerDeserializer $serializer;

    protected function setUp(): void
    {
        $this->serializer = new ProtobufSerializerDeserializer();
    }

    public function testSerializesToProtobufBinary(): void
    {
        $message = new StringValue();
        $message->setValue('hello');

        $result = $this->serializer->serialize($message, 'application/grpc');

        $this->assertSame($message->serializeToString(), $result);
    }

    public function testSerializesToJsonWhenContentTypeIsGrpcJson(): void
    {
        $message = new StringValue();
        $message->setValue('hello');

        $result = $this->serializer->serialize($message, 'application/grpc+json');

        $this->assertSame($message->serializeToJsonString(), $result);
    }

    public function testDeserializeStripsGrpcFramingAndParsesProtobuf(): void
    {
        $original = new StringValue();
        $original->setValue('world');
        $binary = $original->serializeToString();

        // Build gRPC-framed payload: 1 byte flag + 4 bytes length + message
        $framed = pack('CN', 0, strlen($binary)) . $binary;

        /** @var StringValue $result */
        $result = $this->serializer->deserialize($framed, StringValue::class, 'application/grpc');

        $this->assertSame('world', $result->getValue());
    }

    public function testDeserializeReturnsEmptyMessageWhenPayloadTooShort(): void
    {
        /** @var StringValue $result */
        $result = $this->serializer->deserialize('tiny', StringValue::class, 'application/grpc');

        $this->assertInstanceOf(StringValue::class, $result);
        $this->assertSame('', $result->getValue());
    }
}

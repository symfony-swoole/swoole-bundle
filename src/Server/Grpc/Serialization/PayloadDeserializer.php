<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Grpc\Serialization;

use Google\Protobuf\Internal\Message;

/**
 * Interface for deserializing payloads to protobuf message.
 */
interface PayloadDeserializer
{
    /**
     * Deserialize a payload into a message.
     *
     * @param string $payload The payload to deserialize
     * @param class-string<Message> $messageClass The message class to deserialize into
     * @param string $contentType The gRPC content-type (for detection)
     * @return Message The deserialized message
     */
    public function deserialize(string $payload, string $messageClass, string $contentType): Message;
}

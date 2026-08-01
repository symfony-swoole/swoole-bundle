<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Grpc\Serialization;

use Google\Protobuf\Internal\Message;

/**
 * Interface for serializing protobuf messages to string payloads.
 */
interface PayloadSerializer
{
    /**
     * Serialize a message to string.
     *
     * @param Message $message The message to serialize
     * @param string $contentType The gRPC content-type (for detection)
     * @return string The serialized payload
     */
    public function serialize(Message $message, string $contentType): string;
}

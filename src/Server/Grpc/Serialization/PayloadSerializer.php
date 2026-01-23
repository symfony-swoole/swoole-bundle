<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Grpc\Serialization;

use Google\Protobuf\Internal\Message;
use SwooleBundle\SwooleBundle\Server\Grpc\Context\Context;

/**
 * Interface for serializing protobuf messages to string payloads.
 */
interface PayloadSerializer
{
    /**
     * Serialize a message to string.
     *
     * @param Message $message The message to serialize
     * @param Context $context The gRPC context (for content-type detection)
     * @return string The serialized payload
     */
    public function serialize(Message $message, Context $context): string;
}

<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Grpc\Serialization;

use Exception;
use Google\Protobuf\Internal\Message;
use InvalidArgumentException;
use SwooleBundle\SwooleBundle\Server\Grpc\Context\Context;
use SwooleBundle\SwooleBundle\Server\Grpc\ValueObject\ContentType;

/**
 * Default implementation for serializing and deserializing protobuf messages.
 *
 * Supports both protobuf binary and JSON formats based on content-type.
 */
final class ProtobufSerializerDeserializer implements PayloadSerializer, PayloadDeserializer
{
    public function serialize(Message $message, Context $context): string
    {
        $contentType = $this->getContentType($context);

        return $contentType->isJson()
            ? $message->serializeToJsonString()
            : $message->serializeToString();
    }

    /**
     * @throws Exception
     */
    public function deserialize(string $payload, string $messageClass, Context $context): Message
    {
        /** @var Message $message */
        $message = new $messageClass();

        if ($payload === null || $payload === '') {
            return $message;
        }

        $contentType = $this->getContentType($context);

        if ($contentType->isJson()) {
            $message->mergeFromJsonString($payload);
        } else {
            $message->mergeFromString($payload);
        }

        return $message;
    }

    /**
     * Get the content type from context.
     */
    private function getContentType(Context $context): ContentType
    {
        $contentTypeString = $context->getRequest()->getContentType();

        try {
            return ContentType::fromString($contentTypeString);
        } catch (InvalidArgumentException) {
            // Default to protobuf if content type is invalid
            return ContentType::GRPC;
        }
    }
}

<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Grpc\HttpFoundation;

use Google\Protobuf\Internal\Message;
use SwooleBundle\SwooleBundle\Server\Grpc\Serialization\PayloadSerializer;
use Symfony\Component\HttpFoundation\Response;

final class GrpcResponse extends Response
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        Message $message,
        PayloadSerializer $serializer,
        string $contentType,
        array $headers = [],
    ) {
        parent::__construct($serializer->serialize($message, $contentType), Response::HTTP_OK, $headers);
    }
}

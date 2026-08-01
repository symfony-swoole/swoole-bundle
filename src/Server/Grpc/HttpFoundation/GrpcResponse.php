<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Grpc\HttpFoundation;

use Symfony\Component\HttpFoundation\Response;

final class GrpcResponse extends Response
{
    public function __construct(
        string $serializedMessage,
    ) {
        parent::__construct($serializedMessage, Response::HTTP_OK);
    }
}

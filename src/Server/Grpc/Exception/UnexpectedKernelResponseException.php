<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Grpc\Exception;

use SwooleBundle\SwooleBundle\Server\Grpc\Enum\Status;
use SwooleBundle\SwooleBundle\Server\Grpc\HttpFoundation\GrpcResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class UnexpectedKernelResponseException extends GRPCException
{
    public static function fromResponse(Response $response, ?Throwable $previous = null): static
    {
        return self::create(
            $previous?->getMessage() ?? 'Unexpected response type: ' . $response::class . ', expected: ' . GrpcResponse::class,
            Status::fromHttpStatus($response->getStatusCode()),
            $previous,
        );
    }
}

<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Grpc\Writer;

use Swoole\Http\Response;
use SwooleBundle\SwooleBundle\Server\Grpc\Enum\Status;

interface GrpcResponseWriterInterface
{
    public function writeError(Response $response, Status $status, string $message, string $contentType): void;

    public function write(
        Response $response,
        string $payload,
        Status $status = Status::OK,
        string $message = 'OK',
        string $contentType = 'application/grpc',
    ): void;
}

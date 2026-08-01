<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Grpc\Exception;

use Swoole\Http\Request;
use Swoole\Http\Response;
use SwooleBundle\SwooleBundle\Server\Grpc\Enum\Status;
use SwooleBundle\SwooleBundle\Server\Grpc\Writer\GrpcResponseWriterInterface;
use SwooleBundle\SwooleBundle\Server\RequestHandler\ExceptionHandler\ExceptionHandler;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final readonly class GrpcExceptionHandler implements ExceptionHandler
{
    public function __construct(
        private GrpcResponseWriterInterface $responseWriter,
    ) {}

    public function handle(Request $request, Throwable $exception, Response $response): void
    {
        $contentType = $request->header['content-type'] ?? 'application/grpc';

        $this->responseWriter->writeError(
            $response,
            $this->resolveStatus($exception),
            $exception->getMessage(),
            $contentType,
        );
    }

    private function resolveStatus(Throwable $exception): Status
    {
        if ($exception instanceof GRPCException) {
            return Status::from($exception->getCode());
        }

        if ($exception instanceof HttpExceptionInterface) {
            return Status::fromHttpStatus($exception->getStatusCode());
        }

        return Status::INTERNAL;
    }
}

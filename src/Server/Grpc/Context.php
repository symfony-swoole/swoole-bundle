<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Grpc;

use Swoole\Http\Request as SwooleRequest;
use SwooleBundle\SwooleBundle\Server\Grpc\Enum\ContentType;
use SwooleBundle\SwooleBundle\Server\Grpc\Enum\Status;
use SwooleBundle\SwooleBundle\Server\Grpc\Exception\InvokeException;

final readonly class Context
{
    private string $contentType;

    public function __construct(
        private SwooleRequest $request,
    ) {
        $this->validateRequest();
        $this->contentType = $this->request->header['content-type'];
    }

    /**
     * Get the content-type header from the request.
     */
    public function getContentType(): string
    {
        return $this->contentType;
    }

    /**
     * Validate the Swoole HTTP request headers for gRPC compliance.
     *
     * @throws InvokeException if required headers are missing or content-type is not supported
     */
    private function validateRequest(): void
    {
        if (!isset($this->request->header['content-type'], $this->request->header['te'])) {
            throw InvokeException::create(
                'Illegal GRPC request, missing content-type or te header',
                Status::INVALID_ARGUMENT
            );
        }

        if (ContentType::tryFrom($this->request->header['content-type']) === null) {
            throw InvokeException::create(
                "Content-type not supported: {$this->request->header['content-type']}",
                Status::INTERNAL
            );
        }
    }
}

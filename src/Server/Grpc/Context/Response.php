<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Grpc\Context;

/**
 * Class Response
 *
 * Represents a gRPC response, providing access to status, message, payload, and the underlying Swoole response.
 */
final class Response
{
    private int $status = 0;

    private string $message = '';

    private string $payload = '';

    /**
     * Response constructor.
     *
     * @param \Swoole\Http\Response $rawResponse the underlying Swoole HTTP response object
     */
    public function __construct(
        protected \Swoole\Http\Response $rawResponse,
    ) {
    }

    /**
     * Get the response status code.
     */
    public function getStatus(): int
    {
        return $this->status;
    }

    /**
     * Get the response message.
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * Set the response status code.
     */
    public function withStatus(int $status): self
    {
        $this->status = $status;
        return $this;
    }

    /**
     * Set the response message.
     */
    public function withMessage(string $message): self
    {
        $this->message = $message;
        return $this;
    }

    /**
     * Get the underlying Swoole HTTP response object.
     */
    public function getSwooleResponse(): \Swoole\Http\Response
    {
        return $this->rawResponse;
    }

    /**
     * Get the response payload.
     */
    public function getPayload(): string
    {
        return $this->payload;
    }

    /**
     * Set the response payload.
     */
    public function setPayload(string $payload): self
    {
        $this->payload = $payload;

        return $this;
    }
}

<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Grpc\Context;


use Google\Protobuf\Internal\Message;
use SwooleBundle\SwooleBundle\Server\HttpServer;

/**
 * Interface ContextInterface
 *
 * Defines the contract for a gRPC context, including request, response, and message push operations.
 */
interface ContextInterface
{
    /**
     * Get the request object associated with this context.
     */
    public function getRequest(): Request;

    /**
     * Get the response object associated with this context.
     */
    public function getResponse(): Response;

    /**
     * Push a message to the client stream.
     */
    public function push(Message $message): bool;

    /**
     * Get the server instance associated with this context.
     */
    public function getServer(): HttpServer;

    /**
     * Set an attribute in the context.
     *
     * @param string $name attribute name
     * @param mixed $value attribute value
     */
    public function withAttribute(string $name, mixed $value): self;

    /**
     * Get an attribute from the context.
     *
     * @param string $name attribute name
     * @return mixed|null attribute value or null if not set
     */
    public function getAttribute(string $name): mixed;
}

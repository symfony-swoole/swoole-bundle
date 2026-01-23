<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Grpc\Context;

use Google\Protobuf\Internal\Message;
use SwooleBundle\SwooleBundle\Server\Grpc\Exception\InvokeException;
use SwooleBundle\SwooleBundle\Server\Grpc\Service\ServiceMethodDefinition;
use SwooleBundle\SwooleBundle\Server\Grpc\Status;
use SwooleBundle\SwooleBundle\Server\HttpServer;

/**
 * Class Context
 *
 * Represents the context for a gRPC call, holding server, request, response, and attributes.
 * Provides methods to manipulate and retrieve context data throughout the request lifecycle.
 */
final class Context implements ContextInterface
{
    protected array $attributes = [];

    /**
     * Context constructor.
     *
     * @param HttpServer $server the gRPC server instance
     * @param Request $request the request object
     * @param Response $response the response object
     */
    public function __construct(
        protected HttpServer $server,
        protected Request $request,
        protected Response $response,
    ) {
    }

    /**
     * Push a message to the client stream.
     *
     * @param Message $message the message to push
     * @return bool true on success, false otherwise
     * @throws InvokeException if the message type does not match the method definition
     */
    public function push(Message $message): bool
    {
        /**
         * @var ServiceMethodDefinition|null $methodDefinition
         */
        $methodDefinition = $this->getAttribute(ContextKeys::SERVICE_METHOD_DEFINITION);
        if ($methodDefinition && $methodDefinition->returnType !== $message::class) {
            throw InvokeException::create('Internal error', Status::INTERNAL);
        }
        return $this->server->push($this, $message);
    }

    /**
     * Set an attribute in the context.
     *
     * @param string $name attribute name
     * @param mixed $value attribute value
     */
    public function withAttribute(string $name, mixed $value): self
    {
        $this->attributes[$name] = $value;

        return $this;
    }

    /**
     * Get an attribute from the context.
     *
     * @param string $name attribute name
     * @return mixed|null attribute value or null if not set
     */
    public function getAttribute(string $name): mixed
    {
        if (empty($this->attributes[$name])) {
            return null;
        }

        return $this->attributes[$name];
    }

    /**
     * Get the server instance associated with this context.
     */
    public function getServer(): HttpServer
    {
        return $this->server;
    }

    /**
     * Get the request object associated with this context.
     */
    public function getRequest(): Request
    {
        return $this->request;
    }

    /**
     * Get the response object associated with this context.
     */
    public function getResponse(): Response
    {
        return $this->response;
    }

    /**
     * Initialize the request object in the context.
     */
    public function init(): void
    {
        $this->request->init();
    }
}

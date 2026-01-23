<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Grpc\CallHandler;

use Google\Protobuf\Internal\Message;
use SwooleBundle\SwooleBundle\Server\Grpc\Constant;
use SwooleBundle\SwooleBundle\Server\Grpc\Context\Context;
use SwooleBundle\SwooleBundle\Server\Grpc\Exception\InvokeException;
use SwooleBundle\SwooleBundle\Server\Grpc\Service\ServiceMethodDefinition;
use SwooleBundle\SwooleBundle\Server\Grpc\Status;
use Throwable;
use TypeError;

/**
 * Handler for server-streaming gRPC calls.
 */
final class ServerStreamCallHandler implements CallHandler
{
    public function handle(
        Context $context,
        object $service,
        ServiceMethodDefinition $methodDefinition,
        Message $message,
    ): Context {
        $method = $methodDefinition->name;
        $callable = [$service, $method];

        try {
            // Create stream response instance
            $streamReply = new ($methodDefinition->streamType)($context);

            // Execute the streaming method
            $callable($context, $message, $streamReply);

            // For streaming, the response is sent incrementally
            $context->getResponse()
                ->setPayload('')
                ->withStatus(Status::OK)
                ->withMessage('OK');

            return $context;
        } catch (TypeError $e) {
            throw InvokeException::create(
                "Type error in streaming method {$method}: {$e->getMessage()}",
                Status::INTERNAL,
                $e
            );
        } catch (Throwable $e) {
            throw InvokeException::create(
                "Error executing streaming method {$method}: {$e->getMessage()}",
                Status::INTERNAL,
                $e
            );
        }
    }

    public function supports(ServiceMethodDefinition $methodDefinition): bool
    {
        return $methodDefinition->type === Constant::GRPC_CALL_TYPE_STREAM;
    }
}

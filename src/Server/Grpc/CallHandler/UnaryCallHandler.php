<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Grpc\CallHandler;

use Google\Protobuf\Internal\Message;
use SwooleBundle\SwooleBundle\Server\Grpc\Constant;
use SwooleBundle\SwooleBundle\Server\Grpc\Context\Context;
use SwooleBundle\SwooleBundle\Server\Grpc\Exception\InvokeException;
use SwooleBundle\SwooleBundle\Server\Grpc\Serialization\PayloadSerializer;
use SwooleBundle\SwooleBundle\Server\Grpc\Service\ServiceMethodDefinition;
use SwooleBundle\SwooleBundle\Server\Grpc\Status;
use Throwable;
use TypeError;

/**
 * Handler for unary gRPC calls.
 */
final readonly class UnaryCallHandler implements CallHandler
{
    public function __construct(
        private PayloadSerializer $serializer,
    ) {
    }

    public function handle(
        Context $context,
        object $service,
        ServiceMethodDefinition $methodDefinition,
        Message $message,
    ): Context {
        $method = $methodDefinition->name;
        $callable = [$service, $method];

        try {
            /** @var Message $result */
            $result = $callable($context, $message);

            // Serialize the response
            $output = $this->serializer->serialize($result, $context);

            $context->getResponse()
                ->setPayload($output)
                ->withStatus(Status::OK)
                ->withMessage('OK');

            return $context;
        } catch (TypeError $e) {
            throw InvokeException::create("Type error in method {$method}: {$e->getMessage()}", Status::INTERNAL, $e);
        } catch (Throwable $e) {
            throw InvokeException::create("Error executing method {$method}: {$e->getMessage()}", Status::INTERNAL, $e);
        }
    }

    public function supports(ServiceMethodDefinition $methodDefinition): bool
    {
        return $methodDefinition->type === Constant::GRPC_CALL_TYPE_UNARY;
    }
}

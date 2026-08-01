<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Grpc\ArgumentResolver;

use Google\Protobuf\Internal\Message;
use SwooleBundle\SwooleBundle\Server\Grpc\Serialization\PayloadDeserializer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

final readonly class GrpcMessageValueResolver implements ValueResolverInterface
{
    public function __construct(
        private PayloadDeserializer $protobufSerializer,
    ) {}

    /**
     * @return iterable<Message>
     */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $type = $argument->getType();

        if ($type === null || !is_subclass_of($type, Message::class, true)) {
            return;
        }

        $content = $request->getContent();
        $contentType = $request->headers->get('content-type') ?? '';

        /** @var class-string<Message> $type */
        yield $this->protobufSerializer->deserialize($content, $type, $contentType);
    }
}

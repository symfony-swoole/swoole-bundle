<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Server\Grpc\Service\Stub;

use Exception;
use SwooleBundle\SwooleBundle\Server\Grpc\Context\ContextInterface;
use SwooleBundle\SwooleBundle\Server\Grpc\GrpcService;

final class StubService implements GrpcService
{
    public const NAME = '/stub.Service';

    public function UnaryMethod(ContextInterface $context, StubMessage $request): StubMessage
    {
        return new StubMessage();
    }

    public function StreamMethod(ContextInterface $context, StubMessage $request, StubStreamResponse $response): void
    {
        // streaming logic
    }

    public function ErrorMethod(ContextInterface $context, StubMessage $request): StubMessage
    {
        throw new Exception('Service error');
    }
}

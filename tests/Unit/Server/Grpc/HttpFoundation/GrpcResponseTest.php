<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Server\Grpc\HttpFoundation;

use PHPUnit\Framework\TestCase;
use SwooleBundle\SwooleBundle\Server\Grpc\HttpFoundation\GrpcResponse;
use Symfony\Component\HttpFoundation\Response;

final class GrpcResponseTest extends TestCase
{
    public function testResponseHasHttpOkStatus(): void
    {
        $response = new GrpcResponse('');

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testGetContentReturnsSerializedPayload(): void
    {
        $response = new GrpcResponse('serialized-bytes');

        $this->assertSame('serialized-bytes', $response->getContent());
    }

    public function testResponseExtendsSymfonyResponse(): void
    {
        $response = new GrpcResponse('');

        $this->assertInstanceOf(Response::class, $response);
    }
}

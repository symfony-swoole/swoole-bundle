<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Server\Grpc;

use PHPUnit\Framework\TestCase;
use Swoole\Http\Request as SwooleRequest;
use SwooleBundle\SwooleBundle\Server\Grpc\Context;
use SwooleBundle\SwooleBundle\Server\Grpc\Exception\InvokeException;

final class ContextTest extends TestCase
{
    public function testValidateRequestWithValidHeaders(): void
    {
        $request = $this->createMockSwooleRequest(
            [],
            [
                'content-type' => 'application/grpc',
                'te' => 'trailers',
            ]
        );

        $context = new Context($request);

        $this->assertEquals('application/grpc', $context->getContentType());
    }

    public function testValidateRequestWithGrpcProtoContentType(): void
    {
        $request = $this->createMockSwooleRequest(
            [],
            [
                'content-type' => 'application/grpc+proto',
                'te' => 'trailers',
            ]
        );

        $context = new Context($request);

        $this->assertEquals('application/grpc+proto', $context->getContentType());
    }

    public function testValidateRequestWithGrpcJsonContentType(): void
    {
        $request = $this->createMockSwooleRequest(
            [],
            [
                'content-type' => 'application/grpc+json',
                'te' => 'trailers',
            ]
        );

        $context = new Context($request);

        $this->assertEquals('application/grpc+json', $context->getContentType());
    }

    public function testValidateRequestThrowsWhenContentTypeIsMissing(): void
    {
        $request = $this->createMockSwooleRequest(
            [],
            ['te' => 'trailers']
        );


        $this->expectException(InvokeException::class);
        $this->expectExceptionMessage('Illegal GRPC request, missing content-type or te header');

        new Context($request);
    }

    public function testValidateRequestThrowsWhenTeHeaderIsMissing(): void
    {
        $request = $this->createMockSwooleRequest(
            [],
            ['content-type' => 'application/grpc']
        );


        $this->expectException(InvokeException::class);
        $this->expectExceptionMessage('Illegal GRPC request, missing content-type or te header');

        new Context($request);
    }

    public function testValidateRequestThrowsWhenContentTypeIsNotSupported(): void
    {
        $request = $this->createMockSwooleRequest(
            [],
            [
                'content-type' => 'application/json',
                'te' => 'trailers',
            ]
        );


        $this->expectException(InvokeException::class);
        $this->expectExceptionMessage('Content-type not supported: application/json');

        new Context($request);
    }

    private function createMockSwooleRequest(array $server = [], array $header = []): SwooleRequest
    {
        $request = new SwooleRequest();
        $request->server = $server;
        $request->header = $header;

        return $request;
    }
}

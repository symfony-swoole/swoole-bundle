<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Server\Grpc\Exception;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Swoole\Http\Request as SwooleRequest;
use Swoole\Http\Response as SwooleResponse;
use SwooleBundle\SwooleBundle\Server\Grpc\Enum\Status;
use SwooleBundle\SwooleBundle\Server\Grpc\Exception\GRPCException;
use SwooleBundle\SwooleBundle\Server\Grpc\Exception\GrpcExceptionHandler;
use SwooleBundle\SwooleBundle\Server\Grpc\Writer\GrpcResponseWriterInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class GrpcExceptionHandlerTest extends TestCase
{
    private MockObject&GrpcResponseWriterInterface $responseWriter;
    private GrpcExceptionHandler $handler;
    private SwooleRequest $request;
    private SwooleResponse $response;

    protected function setUp(): void
    {
        $this->responseWriter = $this->createMock(GrpcResponseWriterInterface::class);
        $this->handler = new GrpcExceptionHandler($this->responseWriter);

        $this->request = new SwooleRequest();
        $this->request->header = ['content-type' => 'application/grpc+proto'];

        $this->response = $this->createMock(SwooleResponse::class);
    }

    public function testGrpcExceptionStatusIsForwarded(): void
    {
        $this->responseWriter->expects($this->once())
            ->method('writeError')
            ->with($this->response, Status::NOT_FOUND, 'not found', 'application/grpc+proto');

        $this->handler->handle(
            $this->request,
            GRPCException::create('not found', Status::NOT_FOUND),
            $this->response,
        );
    }

    public function testHttpNotFoundExceptionMapsToNotFound(): void
    {
        $this->responseWriter->expects($this->once())
            ->method('writeError')
            ->with($this->response, Status::NOT_FOUND, 'route not found', 'application/grpc+proto');

        $this->handler->handle(
            $this->request,
            new NotFoundHttpException('route not found'),
            $this->response,
        );
    }

    public function testHttpAccessDeniedExceptionMapsToPermissionDenied(): void
    {
        $this->responseWriter->expects($this->once())
            ->method('writeError')
            ->with($this->response, Status::PERMISSION_DENIED, 'forbidden', 'application/grpc+proto');

        $this->handler->handle(
            $this->request,
            new AccessDeniedHttpException('forbidden'),
            $this->response,
        );
    }

    public function testGenericExceptionMapsToInternal(): void
    {
        $this->responseWriter->expects($this->once())
            ->method('writeError')
            ->with($this->response, Status::INTERNAL, 'something broke', 'application/grpc+proto');

        $this->handler->handle(
            $this->request,
            new RuntimeException('something broke'),
            $this->response,
        );
    }

    public function testExceptionMessageIsForwardedAsGrpcMessage(): void
    {
        $this->responseWriter->expects($this->once())
            ->method('writeError')
            ->with($this->response, $this->anything(), 'custom error message', $this->anything());

        $this->handler->handle(
            $this->request,
            new RuntimeException('custom error message'),
            $this->response,
        );
    }

    public function testContentTypeFromRequestIsForwarded(): void
    {
        $this->responseWriter->expects($this->once())
            ->method('writeError')
            ->with($this->response, $this->anything(), $this->anything(), 'application/grpc+proto');

        $this->handler->handle(
            $this->request,
            new RuntimeException('error'),
            $this->response,
        );
    }
}

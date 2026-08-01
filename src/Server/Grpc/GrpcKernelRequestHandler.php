<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Grpc;

use Swoole\Http\Request as SwooleRequest;
use Swoole\Http\Response as SwooleResponse;
use SwooleBundle\SwooleBundle\Bridge\Symfony\HttpFoundation\RequestFactory;
use SwooleBundle\SwooleBundle\Server\Grpc\Enum\ContentType;
use SwooleBundle\SwooleBundle\Server\Grpc\Enum\Status;
use SwooleBundle\SwooleBundle\Server\Grpc\EventListener\GrpcExceptionCapturingSubscriber;
use SwooleBundle\SwooleBundle\Server\Grpc\Exception\GRPCException;
use SwooleBundle\SwooleBundle\Server\Grpc\Exception\UnexpectedKernelResponseException;
use SwooleBundle\SwooleBundle\Server\Grpc\HttpFoundation\GrpcResponse;
use SwooleBundle\SwooleBundle\Server\Grpc\Writer\GrpcResponseWriterInterface;
use SwooleBundle\SwooleBundle\Server\RequestHandler\RequestHandler;
use SwooleBundle\SwooleBundle\Server\Runtime\Bootable;
use Symfony\Component\HttpFoundation\Request as HttpFoundationRequest;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\HttpKernel\TerminableInterface;
use Throwable;

final readonly class GrpcKernelRequestHandler implements RequestHandler, Bootable
{
    public function __construct(
        private RequestFactory $requestFactory,
        private GrpcResponseWriterInterface $responseWriter,
        private KernelInterface $kernel,
    ) {}

    /**
     * @inheritDoc
     */
    public function boot(array $runtimeConfiguration = []): void
    {
        $this->kernel->boot();
    }

    public function handle(SwooleRequest $request, SwooleResponse $response): void
    {
        try {
            $context = new Context($request);
        } catch (GRPCException $e) {
            $this->responseWriter->writeError(
                $response,
                Status::from($e->getCode()),
                $e->getMessage(),
                ContentType::GRPC->value
            );

            return;
        }

        $httpFoundationRequest = $this->requestFactory->make($request);
        $this->executeKernelHandle($httpFoundationRequest, $response, $context);
    }

    private function executeKernelHandle(
        HttpFoundationRequest $httpFoundationRequest,
        SwooleResponse $response,
        Context $context,
    ): void {
        $httpFoundationResponse = $this->kernel->handle($httpFoundationRequest);

        if (!$httpFoundationResponse instanceof GrpcResponse) {
            $this->executeKernelTerminate($this->kernel, $httpFoundationRequest, $httpFoundationResponse);

            /** @var Throwable|null $previous */
            $previous = $httpFoundationRequest->attributes->get(GrpcExceptionCapturingSubscriber::ATTRIBUTE_KEY);

            throw UnexpectedKernelResponseException::fromResponse($httpFoundationResponse, $previous);
        }

        $this->responseWriter->write(
            $response,
            (string) $httpFoundationResponse->getContent(),
            contentType: $context->getContentType(),
        );

        $this->executeKernelTerminate($this->kernel, $httpFoundationRequest, $httpFoundationResponse);
    }

    private function executeKernelTerminate(
        KernelInterface $kernel,
        HttpFoundationRequest $httpFoundationRequest,
        Response $httpFoundationResponse,
    ): void {
        if (!($kernel instanceof TerminableInterface)) {
            return;
        }

        $kernel->terminate($httpFoundationRequest, $httpFoundationResponse);
    }
}

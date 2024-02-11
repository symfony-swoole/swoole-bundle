<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Symfony\HttpKernel;

use Exception;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Swoole\Http\Request as SwooleRequest;
use Swoole\Http\Response as SwooleResponse;
use SwooleBundle\SwooleBundle\Bridge\Symfony\HttpFoundation\RequestFactory;
use SwooleBundle\SwooleBundle\Bridge\Symfony\HttpFoundation\ResponseProcessor;
use SwooleBundle\SwooleBundle\Bridge\Symfony\HttpFoundation\ResponseProcessorInjector;
use SwooleBundle\SwooleBundle\Bridge\Symfony\HttpKernel\HttpKernelRequestHandler;
use SwooleBundle\SwooleBundle\Bridge\Symfony\HttpKernel\KernelPool;
use Symfony\Component\HttpFoundation\Request as HttpFoundationRequest;
use Symfony\Component\HttpFoundation\Response as HttpFoundationResponse;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\HttpKernel\TerminableInterface;

final class HttpKernelHttpDriverTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @var HttpKernelRequestHandler
     */
    private $httpDriver;

    /**
     * @var ObjectProphecy|ResponseProcessor
     */
    private $responseProcessor;

    /**
     * @var ObjectProphecy|RequestFactory
     */
    private $requestFactoryProphecy;

    /**
     * @var ObjectProphecy|ResponseProcessorInjector
     */
    private $responseProcessorInjectorProphecy;

    /**
     * @var KernelPool|ObjectProphecy
     */
    private $kernelPoolProphecy;

    /**
     * @var KernelInterface|ObjectProphecy|TerminableInterface
     */
    private $kernelProphecy;

    protected function setUp(): void
    {
        $this->kernelProphecy = $this->prophesize(KernelInterface::class);
        $this->kernelPoolProphecy = $this->prophesize(KernelPool::class);
        $this->requestFactoryProphecy = $this->prophesize(RequestFactory::class);
        $this->responseProcessorInjectorProphecy = $this->prophesize(ResponseProcessorInjector::class);
        $this->responseProcessor = $this->prophesize(ResponseProcessor::class);

        /** @var KernelPool $kernelPoolMock */
        $kernelPoolMock = $this->kernelPoolProphecy->reveal();
        /** @var RequestFactory $requestFactoryMock */
        $requestFactoryMock = $this->requestFactoryProphecy->reveal();
        /** @var ResponseProcessorInjector $responseProcessorInjectorMock */
        $responseProcessorInjectorMock = $this->responseProcessorInjectorProphecy->reveal();
        /** @var ResponseProcessor $responseProcessorMock */
        $responseProcessorMock = $this->responseProcessor->reveal();

        $this->httpDriver = new HttpKernelRequestHandler(
            $kernelPoolMock,
            $requestFactoryMock,
            $responseProcessorInjectorMock,
            $responseProcessorMock
        );
    }

    public function testBoot(): void
    {
        $this->kernelPoolProphecy->boot()->shouldBeCalled();

        $this->httpDriver->boot();
    }

    /**
     * @throws Exception
     */
    public function testHandleNonTerminable(): void
    {
        $swooleRequest = new SwooleRequest();
        $swooleResponse = new SwooleResponse();

        $httpFoundationResponse = new HttpFoundationResponse();
        $httpFoundationRequest = new HttpFoundationRequest();

        $this->requestFactoryProphecy->make($swooleRequest)->willReturn($httpFoundationRequest)->shouldBeCalled();
        $this->kernelPoolProphecy->get()->willReturn($this->kernelProphecy)->shouldBeCalled();
        $this->kernelPoolProphecy->return($this->kernelProphecy)->shouldBeCalled();
        $this->kernelProphecy->handle($httpFoundationRequest)->willReturn($httpFoundationResponse)->shouldBeCalled();
        $this->responseProcessorInjectorProphecy->injectProcessor($httpFoundationRequest, $swooleResponse)
            ->shouldBeCalled();
        $this->responseProcessor->process($httpFoundationResponse, $swooleResponse)->shouldBeCalled();

        $this->httpDriver->handle($swooleRequest, $swooleResponse);
    }

    /**
     * @throws Exception
     */
    public function testHandleTerminable(): void
    {
        $this->setUpTerminableKernel();

        $swooleRequest = new SwooleRequest();
        $swooleResponse = new SwooleResponse();

        $httpFoundationResponse = new HttpFoundationResponse();
        $httpFoundationRequest = new HttpFoundationRequest();

        $this->requestFactoryProphecy->make($swooleRequest)->willReturn($httpFoundationRequest)->shouldBeCalled();
        $this->kernelPoolProphecy->get()->willReturn($this->kernelProphecy)->shouldBeCalled();
        $this->kernelPoolProphecy->return($this->kernelProphecy)->shouldBeCalled();
        $this->kernelProphecy->handle($httpFoundationRequest)->willReturn($httpFoundationResponse)->shouldBeCalled();
        $this->responseProcessorInjectorProphecy->injectProcessor($httpFoundationRequest, $swooleResponse)
            ->shouldBeCalled();
        $this->responseProcessor->process($httpFoundationResponse, $swooleResponse)->shouldBeCalled();
        $this->kernelProphecy->terminate($httpFoundationRequest, $httpFoundationResponse)->shouldBeCalled();

        $this->httpDriver->handle($swooleRequest, $swooleResponse);
    }

    private function setUpTerminableKernel(): void
    {
        $this->kernelProphecy = $this->prophesize(KernelInterface::class)->willImplement(TerminableInterface::class);

        /** @var KernelPool $kernelPoolMock */
        $kernelPoolMock = $this->kernelPoolProphecy->reveal();
        /** @var RequestFactory $requestFactoryMock */
        $requestFactoryMock = $this->requestFactoryProphecy->reveal();
        /** @var ResponseProcessorInjector $responseProcessorInjectorMock */
        $responseProcessorInjectorMock = $this->responseProcessorInjectorProphecy->reveal();
        /** @var ResponseProcessor $responseProcessorMock */
        $responseProcessorMock = $this->responseProcessor->reveal();

        $this->httpDriver = new HttpKernelRequestHandler(
            $kernelPoolMock,
            $requestFactoryMock,
            $responseProcessorInjectorMock,
            $responseProcessorMock
        );
    }
}

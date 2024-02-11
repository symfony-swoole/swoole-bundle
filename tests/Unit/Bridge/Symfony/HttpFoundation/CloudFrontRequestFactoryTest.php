<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Symfony\HttpFoundation;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Swoole\Http\Request as SwooleRequest;
use SwooleBundle\SwooleBundle\Bridge\Symfony\HttpFoundation\CloudFrontRequestFactory;
use SwooleBundle\SwooleBundle\Bridge\Symfony\HttpFoundation\RequestFactory;
use Symfony\Component\HttpFoundation\Request as HttpFoundationRequest;

final class CloudFrontRequestFactoryTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @var ObjectProphecy|RequestFactory
     */
    private $decoratedProphecy;

    /**
     * @var CloudFrontRequestFactory
     */
    private $requestFactory;

    protected function setUp(): void
    {
        $this->decoratedProphecy = $this->prophesize(RequestFactory::class);

        /** @var RequestFactory $decoratedMock */
        $decoratedMock = $this->decoratedProphecy->reveal();
        $this->requestFactory = new CloudFrontRequestFactory($decoratedMock);
    }

    public function testHandleNoCloudFrontHeader(): void
    {
        $swooleRequest = new SwooleRequest();
        $httpFoundationRequest = new HttpFoundationRequest();

        $this->decoratedProphecy->make($swooleRequest)->willReturn($httpFoundationRequest)->shouldBeCalled();

        self::assertSame($httpFoundationRequest, $this->requestFactory->make($swooleRequest));
    }

    public function testHandleCloudFrontHeader(): void
    {
        $swooleRequest = new SwooleRequest();
        $httpFoundationRequest = new HttpFoundationRequest(
            [],
            [],
            [],
            [],
            [],
            ['HTTP_CLOUDFRONT_FORWARDED_PROTO' => 'https']
        );

        $this->decoratedProphecy->make($swooleRequest)->willReturn($httpFoundationRequest)->shouldBeCalled();

        self::assertSame($httpFoundationRequest, $this->requestFactory->make($swooleRequest));
        self::assertSame('https', $httpFoundationRequest->headers->get('x_forwarded_proto'));
    }
}

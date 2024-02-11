<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Symfony\HttpFoundation;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Swoole\Http\Response as SwooleResponse;
use SwooleBundle\SwooleBundle\Bridge\Symfony\HttpFoundation\ResponseHeadersAndStatusProcessor;
use SwooleBundle\SwooleBundle\Bridge\Symfony\HttpFoundation\ResponseProcessor;
use Symfony\Component\HttpFoundation\Response as HttpFoundationResponse;

final class ResponseHeadersAndStatusProcessorTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @var ObjectProphecy|ResponseHeadersAndStatusProcessor|null
     */
    protected $responseProcessor;

    /**
     * @var ObjectProphecy|SwooleResponse|null
     */
    protected $swooleResponse;

    protected function setUp(): void
    {
        $this->swooleResponse = $this->prophesize(SwooleResponse::class);
        $decoratedProcessor = $this->prophesize(ResponseProcessor::class);
        $decoratedProcessor
            ->process(Argument::type(HttpFoundationResponse::class), $this->swooleResponse->reveal())
            ->shouldBeCalled();
        $this->responseProcessor = new ResponseHeadersAndStatusProcessor($decoratedProcessor->reveal());
    }

    public function testProcess(): void
    {
        $symfonyResponse = new HttpFoundationResponse(
            'success',
            200,
            [
                'Vary' => [
                    'Content-Type',
                    'Authorization',
                    'Origin',
                ],
            ]
        );

        $swooleResponse = $this->swooleResponse->reveal();
        $this->swooleResponse->status(200)->shouldBeCalled();
        foreach ($symfonyResponse->headers->allPreserveCaseWithoutCookies() as $name => $values) {
            $this->swooleResponse->header($name, implode(', ', $values))->shouldBeCalled();
        }
        $this->responseProcessor->process($symfonyResponse, $swooleResponse);
    }
}

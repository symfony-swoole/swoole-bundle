<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Symfony\HttpFoundation;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Swoole\Http\Response as SwooleResponse;
use SwooleBundle\SwooleBundle\Bridge\Symfony\HttpFoundation\EndResponseProcessor;
use Symfony\Component\HttpFoundation\Response as HttpFoundationResponse;

final class ResponseProcessorTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @var EndResponseProcessor
     */
    protected $responseProcessor;

    /**
     * @var HttpFoundationResponse|null
     */
    protected $symfonyResponse;

    /**
     * @var ObjectProphecy|SwooleResponse|null
     */
    protected $swooleResponse;

    protected function setUp(): void
    {
        $this->responseProcessor = new EndResponseProcessor();
        $this->swooleResponse = $this->prophesize(SwooleResponse::class);
    }

    public function testProcess(): void
    {
        $content = 'success';
        $this->symfonyResponse = new HttpFoundationResponse(
            $content,
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
        $this->swooleResponse->end($content)->shouldBeCalled();
        $this->responseProcessor->process($this->symfonyResponse, $swooleResponse);
    }
}

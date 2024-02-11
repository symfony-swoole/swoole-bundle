<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Symfony\HttpFoundation;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Swoole\Http\Request as SwooleRequest;
use Swoole\Http\Response as SwooleResponse;
use SwooleBundle\SwooleBundle\Bridge\Symfony\HttpFoundation\TrustAllProxiesRequestHandler;
use SwooleBundle\SwooleBundle\Server\RequestHandler\RequestHandler;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

final class TrustAllProxiesRequestHandlerTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @var ObjectProphecy|RequestHandler
     */
    private $decoratedProphecy;

    protected function setUp(): void
    {
        SymfonyRequest::setTrustedProxies([], TrustAllProxiesRequestHandler::HEADER_X_FORWARDED_ALL);
        $this->decoratedProphecy = $this->prophesize(RequestHandler::class);
    }

    /**
     * @return array<string, array{startWith: bool, bootWith: array{trustAllProxies?: bool}, expected: bool}>
     */
    public static function trustOrNotProvider(): array
    {
        return [
            'default not trust, boot trust' => [
                'startWith' => false,
                'bootWith' => ['trustAllProxies' => true],
                'expected' => true,
            ],
            'default trust, boot trust' => [
                'startWith' => true,
                'bootWith' => ['trustAllProxies' => true],
                'expected' => true,
            ],
            'default trust, boot nothing' => [
                'startWith' => true,
                'bootWith' => [],
                'expected' => true,
            ],
            'default not trust, boot nothing' => [
                'startWith' => false,
                'bootWith' => [],
                'expected' => false,
            ],
        ];
    }

    /**
     * @param array{trustAllProxies?: bool} $bootWith
     */
    #[DataProvider('trustOrNotProvider')]
    public function testBooting(bool $startWith, array $bootWith, bool $expected): void
    {
        $handler = $this->withTrustAllProxies($startWith);
        self::assertSame($startWith, $handler->trustAllProxies());

        $handler->boot($bootWith);

        self::assertSame($expected, $handler->trustAllProxies());
    }

    public function testHandleWithoutTrusting(): void
    {
        $handler = $this->withTrustAllProxies(false);

        /** @var SwooleRequest $requestMock */
        $requestMock = $this->prophesize(SwooleRequest::class)->reveal();
        /** @var SwooleResponse $responseMock */
        $responseMock = $this->prophesize(SwooleResponse::class)->reveal();

        $this->decoratedProphecy->handle($requestMock, $responseMock)->shouldBeCalled();

        $handler->handle($requestMock, $responseMock);

        self::assertSame([], SymfonyRequest::getTrustedProxies());
    }

    public function testHandleWithTrusting(): void
    {
        $addr = 'test.localhost';

        $handler = $this->withTrustAllProxies(true);

        /** @var SwooleRequest $requestMock */
        $requestMock = $this->prophesize(SwooleRequest::class)->reveal();
        $requestMock->server['remote_addr'] = $addr;

        /** @var SwooleResponse $responseMock */
        $responseMock = $this->prophesize(SwooleResponse::class)->reveal();

        $this->decoratedProphecy->handle($requestMock, $responseMock)->shouldBeCalled();

        $handler->handle($requestMock, $responseMock);

        self::assertSame(['127.0.0.1', $addr], SymfonyRequest::getTrustedProxies());
    }

    public function withTrustAllProxies(bool $trustAllProxies): TrustAllProxiesRequestHandler
    {
        /** @var RequestHandler $handler */
        $handler = $this->decoratedProphecy->reveal();

        return new TrustAllProxiesRequestHandler($handler, $trustAllProxies);
    }
}

<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Server\Configurator;

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use SwooleBundle\SwooleBundle\Server\Configurator\WithServerStartHandler;
use SwooleBundle\SwooleBundle\Server\HttpServerConfiguration;
use SwooleBundle\SwooleBundle\Server\LifecycleHandler\NoOpServerStartHandler;
use SwooleBundle\SwooleBundle\Tests\Unit\Server\SwooleHttpServerMockFactory;

#[RunTestsInSeparateProcesses]
final class WithServerStartHandlerTest extends TestCase
{
    use ProphecyTrait;

    private NoOpServerStartHandler $noOpServerStartHandler;

    private WithServerStartHandler $configurator;

    private HttpServerConfiguration|ObjectProphecy $httpServerConfigurationMock;

    protected function setUp(): void
    {
        $this->httpServerConfigurationMock = $this->prophesize(HttpServerConfiguration::class);
        $this->noOpServerStartHandler = new NoOpServerStartHandler();

        $this->configurator = new WithServerStartHandler(
            $this->noOpServerStartHandler,
            $this->httpServerConfigurationMock->reveal()
        );
    }

    public function testConfigureNoReactorMode(): void
    {
        $this->httpServerConfigurationMock->isReactorRunningMode()
            ->willReturn(false)
            ->shouldBeCalled();

        $swooleServerOnEventSpy = SwooleHttpServerMockFactory::make();

        $this->configurator->configure($swooleServerOnEventSpy);

        self::assertTrue($swooleServerOnEventSpy->registeredEvent());
        self::assertSame(
            ['start', [$this->noOpServerStartHandler, 'handle']],
            $swooleServerOnEventSpy->registeredEventPair()
        );
    }

    public function testConfigureReactorMode(): void
    {
        $this->httpServerConfigurationMock->isReactorRunningMode()
            ->willReturn(true)
            ->shouldBeCalled();

        $swooleServerOnEventSpy = SwooleHttpServerMockFactory::make();

        $this->configurator->configure($swooleServerOnEventSpy);

        self::assertFalse($swooleServerOnEventSpy->registeredEvent());
    }
}

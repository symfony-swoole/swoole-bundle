<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Server\Configurator;

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use SwooleBundle\SwooleBundle\Server\Configurator\WithTaskHandler;
use SwooleBundle\SwooleBundle\Server\HttpServerConfiguration;
use SwooleBundle\SwooleBundle\Server\TaskHandler\NoOpTaskHandler;
use SwooleBundle\SwooleBundle\Tests\Unit\Server\IntMother;
use SwooleBundle\SwooleBundle\Tests\Unit\Server\SwooleHttpServerMockFactory;

#[RunTestsInSeparateProcesses]
final class WithTaskHandlerTest extends TestCase
{
    use ProphecyTrait;

    private NoOpTaskHandler $noOpTaskHandler;

    private WithTaskHandler $configurator;

    private HttpServerConfiguration|ObjectProphecy $configurationProphecy;

    protected function setUp(): void
    {
        $this->noOpTaskHandler = new NoOpTaskHandler();
        $this->configurationProphecy = $this->prophesize(HttpServerConfiguration::class);

        /** @var HttpServerConfiguration $configurationMock */
        $configurationMock = $this->configurationProphecy->reveal();

        $this->configurator = new WithTaskHandler($this->noOpTaskHandler, $configurationMock);
    }

    public function testConfigure(): void
    {
        $this->configurationProphecy->getTaskWorkerCount()
            ->willReturn(IntMother::randomPositive())
            ->shouldBeCalled();

        $swooleServerOnEventSpy = SwooleHttpServerMockFactory::make();

        $this->configurator->configure($swooleServerOnEventSpy);

        self::assertTrue($swooleServerOnEventSpy->registeredEvent());
        self::assertSame(['task', [$this->noOpTaskHandler, 'handle']], $swooleServerOnEventSpy->registeredEventPair());
    }

    public function testDoNotConfigureWhenNoTaskWorkers(): void
    {
        $this->configurationProphecy->getTaskWorkerCount()
            ->willReturn(0)
            ->shouldBeCalled();

        $swooleServerOnEventSpy = SwooleHttpServerMockFactory::make();

        $this->configurator->configure($swooleServerOnEventSpy);

        self::assertFalse($swooleServerOnEventSpy->registeredEvent());
    }
}

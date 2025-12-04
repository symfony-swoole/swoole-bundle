<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Server\TashHandler;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use SwooleBundle\SwooleBundle\Server\Configurator\WithTaskHandler;
use SwooleBundle\SwooleBundle\Server\HttpServerConfiguration;
use SwooleBundle\SwooleBundle\Server\TaskHandler\NoOpConcurrentTaskHandler;
use SwooleBundle\SwooleBundle\Tests\Unit\Server\IntMother;
use SwooleBundle\SwooleBundle\Tests\Unit\Server\SameClosureAssertion;
use SwooleBundle\SwooleBundle\Tests\Unit\Server\SwooleHttpServerMockFactory;

final class ConcurrentTaskHandlerTest extends TestCase
{
    use SameClosureAssertion;
    use ProphecyTrait;

    private NoOpConcurrentTaskHandler $noOpConcurrentTaskHandler;

    private WithTaskHandler $configurator;

    private HttpServerConfiguration|ObjectProphecy $configurationProphecy;

    protected function setUp(): void
    {
        $this->noOpConcurrentTaskHandler = new NoOpConcurrentTaskHandler();
        $this->configurationProphecy = $this->prophesize(HttpServerConfiguration::class);

        /** @var HttpServerConfiguration $configurationMock */
        $configurationMock = $this->configurationProphecy->reveal();

        $this->configurator = new WithTaskHandler($this->noOpConcurrentTaskHandler, $configurationMock);
    }

    public function testConfigure(): void
    {
        $this->configurationProphecy->getTaskWorkerCount()
            ->willReturn(IntMother::randomPositive())
            ->shouldBeCalled();

        $swooleServerOnEventSpy = SwooleHttpServerMockFactory::make();

        $this->configurator->configure($swooleServerOnEventSpy);

        self::assertTrue($swooleServerOnEventSpy->registeredEvent());
        self::assertSame('task', $swooleServerOnEventSpy->registeredEventPair()[0]);
        self::assertSameClosure($this->noOpConcurrentTaskHandler->handle(...), $swooleServerOnEventSpy->registeredEventPair()[1]);
    }
}

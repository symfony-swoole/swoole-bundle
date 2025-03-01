<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Server\Configurator;

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use SwooleBundle\SwooleBundle\Server\Configurator\WithServerManagerStopHandler;
use SwooleBundle\SwooleBundle\Server\LifecycleHandler\NoOpServerManagerStopHandler;
use SwooleBundle\SwooleBundle\Tests\Unit\Server\SameClosureAssertion;
use SwooleBundle\SwooleBundle\Tests\Unit\Server\SwooleHttpServerMockFactory;

#[RunTestsInSeparateProcesses]
final class WithServerManagerStopHandlerTest extends TestCase
{
    use SameClosureAssertion;

    private NoOpServerManagerStopHandler $noOpServerManagerStopHandler;

    private WithServerManagerStopHandler $configurator;

    protected function setUp(): void
    {
        $this->noOpServerManagerStopHandler = new NoOpServerManagerStopHandler();

        $this->configurator = new WithServerManagerStopHandler($this->noOpServerManagerStopHandler);
    }

    public function testConfigure(): void
    {
        $swooleServerOnEventSpy = SwooleHttpServerMockFactory::make();

        $this->configurator->configure($swooleServerOnEventSpy);

        self::assertTrue($swooleServerOnEventSpy->registeredEvent());
        self::assertSame('ManagerStop', $swooleServerOnEventSpy->registeredEventPair()[0]);
        self::assertSameClosure(
            $this->noOpServerManagerStopHandler->handle(...),
            $swooleServerOnEventSpy->registeredEventPair()[1],
        );
    }
}

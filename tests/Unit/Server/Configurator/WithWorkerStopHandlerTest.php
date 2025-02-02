<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Server\Configurator;

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use SwooleBundle\SwooleBundle\Server\Configurator\WithWorkerStopHandler;
use SwooleBundle\SwooleBundle\Server\WorkerHandler\NoOpWorkerStopHandler;
use SwooleBundle\SwooleBundle\Tests\Unit\Server\SameClosureAssertion;
use SwooleBundle\SwooleBundle\Tests\Unit\Server\SwooleHttpServerMockFactory;

#[RunTestsInSeparateProcesses]
final class WithWorkerStopHandlerTest extends TestCase
{
    use SameClosureAssertion;

    private NoOpWorkerStopHandler $noOpWorkerStopHandler;

    private WithWorkerStopHandler $configurator;

    protected function setUp(): void
    {
        $this->noOpWorkerStopHandler = new NoOpWorkerStopHandler();

        $this->configurator = new WithWorkerStopHandler($this->noOpWorkerStopHandler);
    }

    public function testConfigure(): void
    {
        $swooleServerOnEventSpy = SwooleHttpServerMockFactory::make();

        $this->configurator->configure($swooleServerOnEventSpy);

        self::assertTrue($swooleServerOnEventSpy->registeredEvent());
        self::assertSame('WorkerStop', $swooleServerOnEventSpy->registeredEventPair()[0]);
        self::assertSameClosure(
            $this->noOpWorkerStopHandler->handle(...),
            $swooleServerOnEventSpy->registeredEventPair()[1],
        );
    }
}

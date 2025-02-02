<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Server\Configurator;

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use SwooleBundle\SwooleBundle\Server\Configurator\WithWorkerExitHandler;
use SwooleBundle\SwooleBundle\Server\WorkerHandler\NoOpWorkerExitHandler;
use SwooleBundle\SwooleBundle\Tests\Unit\Server\SameClosureAssertion;
use SwooleBundle\SwooleBundle\Tests\Unit\Server\SwooleHttpServerMockFactory;

#[RunTestsInSeparateProcesses]
final class WithWorkerExitHandlerTest extends TestCase
{
    use SameClosureAssertion;

    private NoOpWorkerExitHandler $noOpWorkerExitHandler;

    private WithWorkerExitHandler $configurator;

    protected function setUp(): void
    {
        $this->noOpWorkerExitHandler = new NoOpWorkerExitHandler();

        $this->configurator = new WithWorkerExitHandler($this->noOpWorkerExitHandler);
    }

    public function testConfigure(): void
    {
        $swooleServerOnEventSpy = SwooleHttpServerMockFactory::make();

        $this->configurator->configure($swooleServerOnEventSpy);

        self::assertTrue($swooleServerOnEventSpy->registeredEvent());
        self::assertSame('WorkerExit', $swooleServerOnEventSpy->registeredEventPair()[0]);
        self::assertSameClosure(
            $this->noOpWorkerExitHandler->handle(...),
            $swooleServerOnEventSpy->registeredEventPair()[1],
        );
    }
}

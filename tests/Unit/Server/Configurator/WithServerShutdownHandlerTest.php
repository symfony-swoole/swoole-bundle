<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Server\Configurator;

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use SwooleBundle\SwooleBundle\Server\Configurator\WithServerShutdownHandler;
use SwooleBundle\SwooleBundle\Server\LifecycleHandler\NoOpServerShutdownHandler;
use SwooleBundle\SwooleBundle\Tests\Unit\Server\SameClosureAssertion;
use SwooleBundle\SwooleBundle\Tests\Unit\Server\SwooleHttpServerMockFactory;

#[RunTestsInSeparateProcesses]
final class WithServerShutdownHandlerTest extends TestCase
{
    use SameClosureAssertion;

    private NoOpServerShutdownHandler $noOpServerShutdownHandler;

    private WithServerShutdownHandler $configurator;

    protected function setUp(): void
    {
        $this->noOpServerShutdownHandler = new NoOpServerShutdownHandler();

        $this->configurator = new WithServerShutdownHandler($this->noOpServerShutdownHandler);
    }

    public function testConfigure(): void
    {
        $swooleServerOnEventSpy = SwooleHttpServerMockFactory::make();

        $this->configurator->configure($swooleServerOnEventSpy);

        self::assertTrue($swooleServerOnEventSpy->registeredEvent());
        self::assertSame('shutdown', $swooleServerOnEventSpy->registeredEventPair()[0]);
        self::assertSameClosure(
            $this->noOpServerShutdownHandler->handle(...),
            $swooleServerOnEventSpy->registeredEventPair()[1],
        );
    }
}

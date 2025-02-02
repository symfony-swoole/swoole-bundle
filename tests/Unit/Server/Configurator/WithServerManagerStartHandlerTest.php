<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Server\Configurator;

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use SwooleBundle\SwooleBundle\Server\Configurator\WithServerManagerStartHandler;
use SwooleBundle\SwooleBundle\Server\LifecycleHandler\NoOpServerManagerStartHandler;
use SwooleBundle\SwooleBundle\Tests\Unit\Server\SameClosureAssertion;
use SwooleBundle\SwooleBundle\Tests\Unit\Server\SwooleHttpServerMockFactory;

#[RunTestsInSeparateProcesses]
final class WithServerManagerStartHandlerTest extends TestCase
{
    use SameClosureAssertion;

    private NoOpServerManagerStartHandler $noOpServerManagerStartHandler;

    private WithServerManagerStartHandler $configurator;

    protected function setUp(): void
    {
        $this->noOpServerManagerStartHandler = new NoOpServerManagerStartHandler();

        $this->configurator = new WithServerManagerStartHandler($this->noOpServerManagerStartHandler);
    }

    public function testConfigure(): void
    {
        $swooleServerOnEventSpy = SwooleHttpServerMockFactory::make();

        $this->configurator->configure($swooleServerOnEventSpy);

        self::assertTrue($swooleServerOnEventSpy->registeredEvent());
        self::assertSame('ManagerStart', $swooleServerOnEventSpy->registeredEventPair()[0]);
        self::assertSameClosure(
            $this->noOpServerManagerStartHandler->handle(...),
            $swooleServerOnEventSpy->registeredEventPair()[1]
        );
    }
}

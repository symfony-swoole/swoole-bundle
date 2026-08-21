<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Server\WorkerHandler;

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use SwooleBundle\SwooleBundle\Server\Runtime\HMR\HotModuleReloadTimer;
use SwooleBundle\SwooleBundle\Server\WorkerHandler\HMRWorkerExitHandler;
use SwooleBundle\SwooleBundle\Server\WorkerHandler\HMRWorkerStartHandler;
use SwooleBundle\SwooleBundle\Tests\Unit\Server\IntMother;
use SwooleBundle\SwooleBundle\Tests\Unit\Server\Runtime\HMR\HMRSpy;
use SwooleBundle\SwooleBundle\Tests\Unit\Server\SwooleServerMockFactory;
use SwooleBundle\SwooleBundle\Tests\Unit\Server\SwooleSpy;

#[RunTestsInSeparateProcesses]
final class HMRWorkerStartHandlerTest extends TestCase
{
    private HMRSpy $hmrSpy;

    private SwooleSpy $swooleFacade;

    private HMRWorkerStartHandler $hmrWorkerStartHandler;

    private HotModuleReloadTimer $timer;

    protected function setUp(): void
    {
        $this->hmrSpy = new HMRSpy();
        $this->swooleFacade = new SwooleSpy();
        $this->timer = new HotModuleReloadTimer($this->swooleFacade);
        $this->hmrWorkerStartHandler = new HMRWorkerStartHandler($this->hmrSpy, $this->timer, 2000);
    }

    public function testTaskWorkerNotRegisterTick(): void
    {
        $serverMock = SwooleServerMockFactory::make(true);

        $this->hmrWorkerStartHandler->handle($serverMock, IntMother::random());

        self::assertFalse($this->swooleFacade->registeredTick());
    }

    public function testWorkerRegisterTick(): void
    {
        $serverMock = SwooleServerMockFactory::make();

        $this->hmrWorkerStartHandler->handle($serverMock, IntMother::random());

        self::assertTrue($this->swooleFacade->registeredTick());
        self::assertNotEmpty($this->swooleFacade->registeredTickTuple());
        self::assertSame(2000, $this->swooleFacade->registeredTickTuple()[0]);
        $this->assertCallbackTriggersTick($this->swooleFacade->registeredTickTuple()[1]);
    }

    /**
     * The reason the timer is stopped at all: a worker still holding a repeating timer has a reactor
     * that never runs out of events, so it cannot exit before max_wait_time force-terminates it.
     */
    public function testWorkerExitClearsTheTick(): void
    {
        $serverMock = SwooleServerMockFactory::make();
        $workerId = IntMother::random();

        $this->hmrWorkerStartHandler->handle($serverMock, $workerId);
        self::assertTrue($this->timer->isRunning());

        (new HMRWorkerExitHandler($this->timer))->handle($serverMock, $workerId);

        self::assertSame([$this->swooleFacade->timerId()], $this->swooleFacade->clearedTimerIds());
        self::assertFalse($this->timer->isRunning());
    }

    /**
     * onWorkerExit is raised more than once per shutdown, and a task worker never started a timer to
     * begin with - neither may turn into a second clear of an id swoole has already reused.
     */
    public function testClearingIsOnlyEverDoneOnceForATimerThatWasStarted(): void
    {
        $serverMock = SwooleServerMockFactory::make();
        $workerId = IntMother::random();
        $exitHandler = new HMRWorkerExitHandler($this->timer);

        $this->hmrWorkerStartHandler->handle($serverMock, $workerId);
        $exitHandler->handle($serverMock, $workerId);
        $exitHandler->handle($serverMock, $workerId);

        self::assertCount(1, $this->swooleFacade->clearedTimerIds());
    }

    public function testTaskWorkerExitClearsNothing(): void
    {
        $serverMock = SwooleServerMockFactory::make(true);
        $workerId = IntMother::random();

        $this->hmrWorkerStartHandler->handle($serverMock, $workerId);
        (new HMRWorkerExitHandler($this->timer))->handle($serverMock, $workerId);

        self::assertSame([], $this->swooleFacade->clearedTimerIds());
    }

    private function assertCallbackTriggersTick(callable $callback): void
    {
        $callback();
        self::assertTrue($this->hmrSpy->ticked());
    }
}

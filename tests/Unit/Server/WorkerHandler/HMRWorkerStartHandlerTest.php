<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Server\WorkerHandler;

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SwooleBundle\SwooleBundle\Server\Runtime\HMR\HotModuleReloader;
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

    /**
     * The timer does not wait for its callback, and a poll yields on file IO, so the next tick can be
     * fired from inside the one before it. That one is skipped rather than polling beside it.
     */
    public function testATickFiredWhileThePreviousOneIsStillRunningIsSkipped(): void
    {
        $reloader = new CountingReloader();
        $onTick = $this->registeredTickOf($reloader);
        $reloader->callDuringNextTick($onTick);

        $onTick();

        self::assertSame(1, $reloader->ticks());

        $onTick();

        self::assertSame(2, $reloader->ticks());
    }

    /**
     * A failed poll must not leave the handler believing one is still running, or HMR stops for good.
     */
    public function testATickThatThrowsDoesNotStopTheNextOne(): void
    {
        $reloader = new CountingReloader();
        $onTick = $this->registeredTickOf($reloader);
        $reloader->throwOnNextTick();

        try {
            $onTick();
            self::fail('The tick was expected to throw.');
        } catch (RuntimeException) {
        }

        $onTick();

        self::assertSame(2, $reloader->ticks());
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

    private function registeredTickOf(HotModuleReloader $reloader): callable
    {
        $swoole = new SwooleSpy();
        $handler = new HMRWorkerStartHandler($reloader, new HotModuleReloadTimer($swoole), 2000);
        $handler->handle(SwooleServerMockFactory::make(), IntMother::random());

        self::assertNotEmpty($swoole->registeredTickTuple());

        return $swoole->registeredTickTuple()[1];
    }
}

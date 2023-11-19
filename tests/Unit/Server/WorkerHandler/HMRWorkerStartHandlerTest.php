<?php

declare(strict_types=1);

namespace K911\Swoole\Tests\Unit\Server\WorkerHandler;

use K911\Swoole\Server\WorkerHandler\HMRWorkerStartHandler;
use K911\Swoole\Tests\Unit\Server\IntMother;
use K911\Swoole\Tests\Unit\Server\Runtime\HMR\HMRSpy;
use K911\Swoole\Tests\Unit\Server\SwooleFacadeSpy;
use K911\Swoole\Tests\Unit\Server\SwooleServerMockFactory;
use PHPUnit\Framework\TestCase;

/**
 * @runTestsInSeparateProcesses
 */
class HMRWorkerStartHandlerTest extends TestCase
{
    private HMRSpy $hmrSpy;

    private SwooleFacadeSpy $swooleFacade;

    private HMRWorkerStartHandler $hmrWorkerStartHandler;

    protected function setUp(): void
    {
        $this->hmrSpy = new HMRSpy();
        $this->swooleFacade = new SwooleFacadeSpy();
        $this->hmrWorkerStartHandler = new HMRWorkerStartHandler($this->hmrSpy, $this->swooleFacade, 2000);
    }

    public function testTaskWorkerNotRegisterTick(): void
    {
        $serverMock = SwooleServerMockFactory::make(true);

        $this->hmrWorkerStartHandler->handle($serverMock, IntMother::random());

        self::assertFalse($this->swooleFacade->registeredTick);
    }

    public function testWorkerRegisterTick(): void
    {
        $serverMock = SwooleServerMockFactory::make();

        $this->hmrWorkerStartHandler->handle($serverMock, IntMother::random());

        self::assertTrue($this->swooleFacade->registeredTick);
        self::assertSame(2000, $this->swooleFacade->registeredTickTuple[0]);
        $this->assertCallbackTriggersTick($this->swooleFacade->registeredTickTuple[1]);
    }

    private function assertCallbackTriggersTick(callable $callback): void
    {
        $callback();
        self::assertTrue($this->hmrSpy->tick);
    }
}

<?php

declare(strict_types=1);

namespace K911\Swoole\Tests\Unit\Server\Configurator;

use K911\Swoole\Server\Configurator\WithWorkerStopHandler;
use K911\Swoole\Server\WorkerHandler\NoOpWorkerStopHandler;
use K911\Swoole\Tests\Unit\Server\SwooleHttpServerMockFactory;
use PHPUnit\Framework\TestCase;

/**
 * @runTestsInSeparateProcesses
 */
class WithWorkerStopHandlerTest extends TestCase
{
    /**
     * @var NoOpWorkerStopHandler
     */
    private $noOpWorkerStopHandler;

    /**
     * @var WithWorkerStopHandler
     */
    private $configurator;

    protected function setUp(): void
    {
        $this->noOpWorkerStopHandler = new NoOpWorkerStopHandler();

        $this->configurator = new WithWorkerStopHandler($this->noOpWorkerStopHandler);
    }

    public function testConfigure(): void
    {
        $swooleServerOnEventSpy = SwooleHttpServerMockFactory::make();

        $this->configurator->configure($swooleServerOnEventSpy);

        self::assertTrue($swooleServerOnEventSpy->registeredEvent);
        self::assertSame(['WorkerStop', [$this->noOpWorkerStopHandler, 'handle']], $swooleServerOnEventSpy->registeredEventPair);
    }
}

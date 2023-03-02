<?php

declare(strict_types=1);

namespace K911\Swoole\Tests\Unit\Server\Configurator;

use K911\Swoole\Server\Configurator\WithWorkerErrorHandler;
use K911\Swoole\Server\WorkerHandler\NoOpWorkerErrorHandler;
use K911\Swoole\Tests\Unit\Server\SwooleHttpServerMock;
use PHPUnit\Framework\TestCase;

/**
 * @runTestsInSeparateProcesses
 */
class WithWorkerErrorHandlerTest extends TestCase
{
    /**
     * @var NoOpWorkerErrorHandler
     */
    private $noOpWorkerErrorHandler;

    /**
     * @var WithWorkerErrorHandler
     */
    private $configurator;

    protected function setUp(): void
    {
        $this->noOpWorkerErrorHandler = new NoOpWorkerErrorHandler();

        $this->configurator = new WithWorkerErrorHandler($this->noOpWorkerErrorHandler);
    }

    public function testConfigure(): void
    {
        $swooleServerOnEventSpy = SwooleHttpServerMock::make();

        $this->configurator->configure($swooleServerOnEventSpy);

        self::assertTrue($swooleServerOnEventSpy->registeredEvent);
        self::assertSame(['WorkerError', [$this->noOpWorkerErrorHandler, 'handle']], $swooleServerOnEventSpy->registeredEventPair);
    }
}

<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Server\Configurator;

use PHPUnit\Framework\TestCase;
use SwooleBundle\SwooleBundle\Server\Configurator\WithWorkerStopHandler;
use SwooleBundle\SwooleBundle\Server\WorkerHandler\NoOpWorkerStopHandler;
use SwooleBundle\SwooleBundle\Tests\Unit\Server\SwooleHttpServerMockFactory;

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

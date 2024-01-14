<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Server\Configurator;

use PHPUnit\Framework\TestCase;
use SwooleBundle\SwooleBundle\Server\Configurator\WithWorkerExitHandler;
use SwooleBundle\SwooleBundle\Server\WorkerHandler\NoOpWorkerExitHandler;
use SwooleBundle\SwooleBundle\Tests\Unit\Server\SwooleHttpServerMockFactory;

/**
 * @runTestsInSeparateProcesses
 */
class WithWorkerExitHandlerTest extends TestCase
{
    /**
     * @var NoOpWorkerExitHandler
     */
    private $noOpWorkerExitHandler;

    /**
     * @var WithWorkerExitHandler
     */
    private $configurator;

    protected function setUp(): void
    {
        $this->noOpWorkerExitHandler = new NoOpWorkerExitHandler();

        $this->configurator = new WithWorkerExitHandler($this->noOpWorkerExitHandler);
    }

    public function testConfigure(): void
    {
        $swooleServerOnEventSpy = SwooleHttpServerMockFactory::make();

        $this->configurator->configure($swooleServerOnEventSpy);

        self::assertTrue($swooleServerOnEventSpy->registeredEvent);
        self::assertSame(['WorkerExit', [$this->noOpWorkerExitHandler, 'handle']], $swooleServerOnEventSpy->registeredEventPair);
    }
}

<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Server\Configurator;

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use SwooleBundle\SwooleBundle\Server\Configurator\WithWorkerStartHandler;
use SwooleBundle\SwooleBundle\Server\WorkerHandler\NoOpWorkerStartHandler;
use SwooleBundle\SwooleBundle\Tests\Unit\Server\SwooleHttpServerMockFactory;

#[RunTestsInSeparateProcesses]
final class WithWorkerStartHandlerTest extends TestCase
{
    /**
     * @var NoOpWorkerStartHandler
     */
    private $noOpWorkerStartHandler;

    /**
     * @var WithWorkerStartHandler
     */
    private $configurator;

    protected function setUp(): void
    {
        $this->noOpWorkerStartHandler = new NoOpWorkerStartHandler();

        $this->configurator = new WithWorkerStartHandler($this->noOpWorkerStartHandler);
    }

    public function testConfigure(): void
    {
        $swooleServerOnEventSpy = SwooleHttpServerMockFactory::make();

        $this->configurator->configure($swooleServerOnEventSpy);

        self::assertTrue($swooleServerOnEventSpy->registeredEvent());
        self::assertSame(
            ['WorkerStart', [$this->noOpWorkerStartHandler, 'handle']],
            $swooleServerOnEventSpy->registeredEventPair()
        );
    }
}

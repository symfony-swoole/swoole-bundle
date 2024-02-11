<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Server\Configurator;

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use SwooleBundle\SwooleBundle\Server\Configurator\WithWorkerErrorHandler;
use SwooleBundle\SwooleBundle\Server\WorkerHandler\NoOpWorkerErrorHandler;
use SwooleBundle\SwooleBundle\Tests\Unit\Server\SwooleHttpServerMockFactory;

#[RunTestsInSeparateProcesses]
final class WithWorkerErrorHandlerTest extends TestCase
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
        $swooleServerOnEventSpy = SwooleHttpServerMockFactory::make();

        $this->configurator->configure($swooleServerOnEventSpy);

        self::assertTrue($swooleServerOnEventSpy->registeredEvent());
        self::assertSame(
            ['WorkerError', [$this->noOpWorkerErrorHandler, 'handle']],
            $swooleServerOnEventSpy->registeredEventPair()
        );
    }
}

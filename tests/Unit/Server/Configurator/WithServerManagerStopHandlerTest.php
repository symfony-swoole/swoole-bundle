<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Server\Configurator;

use PHPUnit\Framework\TestCase;
use SwooleBundle\SwooleBundle\Server\Configurator\WithServerManagerStopHandler;
use SwooleBundle\SwooleBundle\Server\LifecycleHandler\NoOpServerManagerStopHandler;
use SwooleBundle\SwooleBundle\Tests\Unit\Server\SwooleHttpServerMockFactory;

/**
 * @runTestsInSeparateProcesses
 */
class WithServerManagerStopHandlerTest extends TestCase
{
    /**
     * @var NoOpServerManagerStopHandler
     */
    private $noOpServerManagerStopHandler;

    /**
     * @var WithServerManagerStopHandler
     */
    private $configurator;

    protected function setUp(): void
    {
        $this->noOpServerManagerStopHandler = new NoOpServerManagerStopHandler();

        $this->configurator = new WithServerManagerStopHandler($this->noOpServerManagerStopHandler);
    }

    public function testConfigure(): void
    {
        $swooleServerOnEventSpy = SwooleHttpServerMockFactory::make();

        $this->configurator->configure($swooleServerOnEventSpy);

        self::assertTrue($swooleServerOnEventSpy->registeredEvent);
        self::assertSame(['ManagerStop', [$this->noOpServerManagerStopHandler, 'handle']], $swooleServerOnEventSpy->registeredEventPair);
    }
}

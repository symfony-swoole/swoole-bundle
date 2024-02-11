<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Server\Configurator;

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use SwooleBundle\SwooleBundle\Server\Configurator\WithServerManagerStartHandler;
use SwooleBundle\SwooleBundle\Server\LifecycleHandler\NoOpServerManagerStartHandler;
use SwooleBundle\SwooleBundle\Tests\Unit\Server\SwooleHttpServerMockFactory;

#[RunTestsInSeparateProcesses]
final class WithServerManagerStartHandlerTest extends TestCase
{
    /**
     * @var NoOpServerManagerStartHandler
     */
    private $noOpServerManagerStartHandler;

    /**
     * @var WithServerManagerStartHandler
     */
    private $configurator;

    protected function setUp(): void
    {
        $this->noOpServerManagerStartHandler = new NoOpServerManagerStartHandler();

        $this->configurator = new WithServerManagerStartHandler($this->noOpServerManagerStartHandler);
    }

    public function testConfigure(): void
    {
        $swooleServerOnEventSpy = SwooleHttpServerMockFactory::make();

        $this->configurator->configure($swooleServerOnEventSpy);

        self::assertTrue($swooleServerOnEventSpy->registeredEvent());
        self::assertSame(
            ['ManagerStart', [$this->noOpServerManagerStartHandler, 'handle']],
            $swooleServerOnEventSpy->registeredEventPair()
        );
    }
}

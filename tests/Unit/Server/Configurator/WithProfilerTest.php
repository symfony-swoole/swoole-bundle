<?php

declare(strict_types=1);

namespace K911\Swoole\Tests\Unit\Server\Configurator;

use K911\Swoole\Bridge\Upscale\Blackfire\ProfilerActivator;
use K911\Swoole\Bridge\Upscale\Blackfire\WithProfiler;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Swoole\Http\Server;

/**
 * @runTestsInSeparateProcesses
 */
class WithProfilerTest extends TestCase
{
    use ProphecyTrait;

    private WithProfiler $configurator;

    /**
     * @var ObjectProphecy|ProfilerActivator
     */
    private $configurationProphecy;

    protected function setUp(): void
    {
        $this->configurationProphecy = $this->prophesize(ProfilerActivator::class);

        /** @var ProfilerActivator $profileActivatorMock */
        $profileActivatorMock = $this->configurationProphecy->reveal();

        $this->configurator = new WithProfiler($profileActivatorMock);
    }

    public function testProfiler(): void
    {
        $swooleServer = $this->createMock(Server::class);

        $this->configurationProphecy
            ->activate($swooleServer)
            ->shouldBeCalled()
        ;

        $this->configurator->configure($swooleServer);
    }
}

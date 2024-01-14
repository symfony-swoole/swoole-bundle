<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Server\Configurator;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Swoole\Http\Server;
use SwooleBundle\SwooleBundle\Bridge\Upscale\Blackfire\Profiling\ProfilerActivator;
use SwooleBundle\SwooleBundle\Bridge\Upscale\Blackfire\Profiling\WithProfiler;

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

<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Feature;

use Override;
use SwooleBundle\SwooleBundle\Bridge\Upscale\Blackfire\Monitoring\Apm;
use SwooleBundle\SwooleBundle\Bridge\Upscale\Blackfire\Monitoring\WithApm;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Test\ServerTestCase;
use Symfony\Bundle\FrameworkBundle\Test\TestContainer;

final class BlackfireMonitoringRegisteredTest extends ServerTestCase
{
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->deleteVarDirectory();
    }

    /**
     * Ensure that WithProfiler and Profiler are registered.
     */
    public function testWiring(): void
    {
        $kernel = self::createKernel(['environment' => 'blackfire_monitoring']);
        $kernel->boot();

        $container = $kernel->getContainer();
        /** @var TestContainer $testContainer */
        $testContainer = $container->get('test.service_container');

        self::assertTrue($testContainer->has(Apm::class));
        self::assertTrue($testContainer->has(WithApm::class));
    }
}

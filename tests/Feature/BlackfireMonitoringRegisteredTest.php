<?php

declare(strict_types=1);

namespace K911\Swoole\Tests\Feature;

use K911\Swoole\Bridge\Upscale\Blackfire\Monitoring\Apm;
use K911\Swoole\Bridge\Upscale\Blackfire\Monitoring\WithApm;
use K911\Swoole\Tests\Fixtures\Symfony\TestBundle\Test\ServerTestCase;

final class BlackfireMonitoringRegisteredTest extends ServerTestCase
{
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
        $kernel = static::createKernel(['environment' => 'blackfire_monitoring']);
        $kernel->boot();

        $container = $kernel->getContainer();
        $testContainer = $container->get('test.service_container');

        self::assertTrue($testContainer->has(Apm::class));
        self::assertTrue($testContainer->has(WithApm::class));
    }
}

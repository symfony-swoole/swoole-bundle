<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Feature;

use SwooleBundle\SwooleBundle\Bridge\Upscale\Blackfire\Profiling\WithProfiler;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Test\ServerTestCase;
use Symfony\Bundle\FrameworkBundle\Test\TestContainer;
use Upscale\Swoole\Blackfire\Profiler;

final class BlackfireProfilerRegisteredTest extends ServerTestCase
{
    /**
     * Ensure that WithProfiler and Profiler are registered.
     */
    public function testWiring(): void
    {
        $kernel = self::createKernel(['environment' => 'dev']);
        $kernel->boot();

        $container = $kernel->getContainer();
        /** @var TestContainer $testContainer */
        $testContainer = $container->get('test.service_container');

        self::assertTrue($testContainer->has(Profiler::class));
        self::assertTrue($testContainer->has(WithProfiler::class));
    }
}

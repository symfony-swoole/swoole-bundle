<?php

declare(strict_types=1);

namespace K911\Swoole\Tests\Feature;

use K911\Swoole\Bridge\Upscale\Blackfire\Monitoring\Apm;
use K911\Swoole\Bridge\Upscale\Blackfire\Monitoring\WithApm;
use K911\Swoole\Client\HttpClient;
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

    public function testProfilerStartStop(): void
    {
        $this->markTestSkippedIfXdebugEnabled();

        if (\class_exists(\BlackfireProbe::class)) {
            $rc = new \ReflectionClass(\BlackfireProbe::class);

            if ($rc->isInternal()) {
                $this->markTestSkipped(
                    'This test shouldn\'t run with Blackfire extension enabled (which should not be in CI environment)'
                );
            }
        }

        $clearCache = $this->createConsoleProcess([
            'cache:clear',
        ], ['APP_ENV' => 'blackfire_monitoring']);
        $clearCache->setTimeout(10);
        $clearCache->disableOutput();
        $clearCache->run();

        $this->assertProcessSucceeded($clearCache);

        $serverRun = $this->createConsoleProcess([
            'swoole:server:run',
            '--host=localhost',
            '--port=9999',
        ], ['APP_ENV' => 'blackfire_monitoring']);

        $serverRun->setTimeout(10);
        $serverRun->start();

        $this->runAsCoroutineAndWait(function (): void {
            $client = HttpClient::fromDomain('localhost', 9999, false);
            $this->assertTrue($client->connect());

            $response = $client->send('/blackfire/index')['response'];
            $this->assertSame(200, $response['statusCode']);
            $expected = ['started' => true, 'stopped' => true];
            $this->assertSame($expected, $response['body']);
        });

        $serverRun->stop();
    }
}

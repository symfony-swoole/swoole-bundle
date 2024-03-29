<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Feature;

use ReflectionClass;
use SwooleBundle\SwooleBundle\Bridge\Tideways\Apm\Apm;
use SwooleBundle\SwooleBundle\Bridge\Tideways\Apm\WithApm;
use SwooleBundle\SwooleBundle\Client\HttpClient;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Test\ServerTestCase;
use Tideways\Profiler;

final class TidewaysProfilerRegisteredTest extends ServerTestCase
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
        $kernel = self::createKernel(['environment' => 'tideways']);
        $kernel->boot();

        $container = $kernel->getContainer();
        $testContainer = $container->get('test.service_container');

        self::assertTrue($testContainer->has(Apm::class));
        self::assertTrue($testContainer->has(WithApm::class));
    }

    public function testProfilerStartStop(): void
    {
        $this->markTestSkippedIfXdebugEnabled();

        if (class_exists(Profiler::class)) {
            $rc = new ReflectionClass(Profiler::class);

            if ($rc->isInternal()) {
                $this->markTestSkipped(
                    'This test shouldn\'t run with Tideways extension enabled (which should not be in CI environment)'
                );
            }
        }

        $clearCache = $this->createConsoleProcess([
            'cache:clear',
        ], ['APP_ENV' => 'tideways']);
        $clearCache->setTimeout(10);
        $clearCache->disableOutput();
        $clearCache->run();

        $this->assertProcessSucceeded($clearCache);

        $serverRun = $this->createConsoleProcess([
            'swoole:server:run',
            '--host=localhost',
            '--port=9999',
        ], ['APP_ENV' => 'tideways']);

        $serverRun->setTimeout(10);
        $serverRun->start();

        $this->runAsCoroutineAndWait(function (): void {
            $client = HttpClient::fromDomain('localhost', 9999, false);
            $this->assertTrue($client->connect(3, 1, true));

            $response = $client->send('/tideways/index')['response'];

            $this->assertSame(200, $response['statusCode']);
            $expected = ['started' => true, 'stopped' => true];
            $this->assertSame($expected, $response['body']);
        });

        $serverRun->stop();
    }
}

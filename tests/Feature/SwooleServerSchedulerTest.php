<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Feature;

use Swoole\Coroutine;
use SwooleBundle\SwooleBundle\Client\HttpClient;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Test\ServerTestCase;

final class SwooleServerSchedulerTest extends ServerTestCase
{
    private const string TEST_FILE_PATH = self::FIXTURE_RESOURCES_DIR . DIRECTORY_SEPARATOR . 'scheduler_ran.txt';

    protected function setUp(): void
    {
        $this->markTestSkippedIfXdebugEnabled();
        $this->deleteVarDirectory();
        @unlink(self::TEST_FILE_PATH);
    }

    protected function tearDown(): void
    {
        @unlink(self::TEST_FILE_PATH);

        parent::tearDown();
    }

    public function testScheduledMessageIsDispatchedOnATimerTick(): void
    {
        self::assertFileDoesNotExist(self::TEST_FILE_PATH);

        // tests/Fixtures/Symfony/app/config/scheduler/swoole.php enables the scheduler
        // configurator with a 1 second poll interval (well below the 60 second default) so this
        // test doesn't have to wait a minute for the first tick.
        $serverRun = $this->createConsoleProcess([
            'swoole:server:run',
            '--host=localhost',
            '--port=9999',
        ], ['APP_ENV' => 'scheduler']);

        $serverRun->setTimeout(10);
        $serverRun->start();

        $this->runAsCoroutineAndWait(function (): void {
            $client = HttpClient::fromDomain('localhost', 9999, false);
            $this->assertTrue($client->connect(3, 1, true));

            Coroutine::sleep($this->coverageEnabled() ? 2 : 3);
        });

        $serverRun->stop();

        self::assertFileExists(self::TEST_FILE_PATH);
        self::assertSame('ran', file_get_contents(self::TEST_FILE_PATH));
    }
}

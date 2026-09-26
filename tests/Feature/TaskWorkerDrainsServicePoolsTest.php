<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Feature;

use Override;
use SwooleBundle\SwooleBundle\Client\HttpClient;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Service\GoodbyeSayingConnection;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Test\ServerTestCase;

/**
 * A pooled service that closes its connection in its destructor, with a read that has to wait - a mail
 * transport's QUIT - is destroyed inside a coroutine when its task worker is recycled.
 *
 * Left in the pool until the process ends, it would be destroyed after the scheduler has gone, and with the
 * runtime hooks on the read is a fatal "API must be called in the coroutine": the worker exits 255 after a clean
 * drain. The runner drains the pools in the worker's last coroutine instead.
 */
final class TaskWorkerDrainsServicePoolsTest extends ServerTestCase
{
    private const int BOOT_TIMEOUT_SECONDS = 10;

    private const float CLOSED_WITHIN_SECONDS = 20.0;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->deleteVarDirectory();
        self::deleteGoodbyeFile();
    }

    #[Override]
    protected function tearDown(): void
    {
        self::deleteGoodbyeFile();

        parent::tearDown();
    }

    public function testAPooledConnectionIsClosedInsideACoroutineWhenItsWorkerIsRecycled(): void
    {
        $serverRun = $this->createConsoleProcess([
            'swoole:server:run',
            '--host=localhost',
            sprintf('--port=%d', self::port()),
        ], ['APP_ENV' => 'task_worker_pool_drain']);
        $serverRun->setTimeout(60);
        $serverRun->start();

        $this->runAsCoroutineAndWait(function (): void {
            $client = HttpClient::fromDomain('localhost', self::port(), false);
            $this->assertTrue($client->connect(self::connectTimeout(self::BOOT_TIMEOUT_SECONDS), 1, true));
        });

        $deadline = microtime(true) + self::CLOSED_WITHIN_SECONDS;

        while (!is_file(GoodbyeSayingConnection::filePath()) && microtime(true) < $deadline) {
            usleep(100_000);
        }

        $serverRun->stop();
        $serverOutput = $serverRun->getOutput() . $serverRun->getErrorOutput();

        self::assertFileExists(
            GoodbyeSayingConnection::filePath(),
            "The recycled worker never closed its pooled connection.\n" . $serverOutput,
        );
        self::assertMatchesRegularExpression(
            '/^closed in coroutine [1-9]\d*$/m',
            (string) file_get_contents(GoodbyeSayingConnection::filePath()),
            'The connection was closed outside any coroutine.',
        );
        self::assertStringNotContainsString('API must be called in the coroutine', $serverOutput);
    }

    private static function deleteGoodbyeFile(): void
    {
        if (!is_file(GoodbyeSayingConnection::filePath())) {
            return;
        }

        unlink(GoodbyeSayingConnection::filePath());
    }
}

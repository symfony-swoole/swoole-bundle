<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Feature;

use ArrayObject;
use Override;
use Swoole\Coroutine;
use SwooleBundle\SwooleBundle\Client\HttpClient;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Command\TaskWorkerHeartbeatCommand;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Test\ServerTestCase;

/**
 * EXPERIMENTAL feature - a task worker command must survive an http worker being recycled.
 *
 * worker_max_request replaces an http worker after so many requests. That worker leaves through the
 * same onWorkerExit as a worker going down with the server, and the stop signal is raised on the
 * generation every live worker shares - so the commands in the task worker stop too, and nothing ever
 * starts them again.
 *
 * @see docs/swoole-task-worker-commands.md
 */
final class TaskWorkerMaxRequestTest extends ServerTestCase
{
    private const string SLOT = 'maxreq';

    /**
     * Comfortably past the configured worker_max_request of 3, because swoole jitters the real
     * threshold upwards from it.
     */
    private const int REQUESTS = 12;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->deleteVarDirectory();
        $this->deleteHeartbeatFile();
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->deleteHeartbeatFile();

        parent::tearDown();
    }

    public function testCommandKeepsRunningWhileHttpWorkersAreRecycled(): void
    {
        $serverRun = $this->createConsoleProcess([
            'swoole:server:run',
            '--host=localhost',
            sprintf('--port=%d', self::port()),
        ], ['APP_ENV' => 'task_worker_max_request']);

        $serverRun->setTimeout(30);
        $serverRun->start();

        $path = TaskWorkerHeartbeatCommand::filePath(self::SLOT);

        /** @var ArrayObject<string, int|string> $ticks */
        $ticks = new ArrayObject();

        $this->runAsCoroutineAndWait(function () use ($path, $ticks): void {
            $client = HttpClient::fromDomain('localhost', self::port(), false);
            $this->assertTrue($client->connect(self::connectTimeout(), 1, true));

            Coroutine::sleep(1);
            $this->assertFileExists($path, 'Command never started in the task worker.');

            for ($i = 0; $i < self::REQUESTS; $i++) {
                // A client of its own per request: a recycled worker drops the connection it was
                // serving on, and reusing one would measure that rather than the recycling.
                HttpClient::fromDomain('localhost', self::port(), false)
                    ->send('/');
            }

            // Long enough for a command that was told to stop to have written its "stopped" line, and
            // for one that was not to have gone on ticking.
            Coroutine::sleep(2);
            $ticks['contents'] = (string) file_get_contents($path);
            $ticks['before'] = substr_count((string) $ticks['contents'], "tick\n");

            Coroutine::sleep(1);
            $ticks['after'] = substr_count((string) file_get_contents($path), "tick\n");
        });

        self::assertStringNotContainsString(
            'stopped',
            (string) $ticks['contents'],
            'Recycling an http worker stopped the command in the task worker. The stop signal cannot '
            . 'tell a worker being replaced from the server going down.',
        );

        self::assertGreaterThan(
            (int) $ticks['before'],
            (int) $ticks['after'],
            'The command stopped ticking after the http worker was recycled.',
        );

        $serverRun->stop();
    }

    private function deleteHeartbeatFile(): void
    {
        $path = TaskWorkerHeartbeatCommand::filePath(self::SLOT);

        if (!file_exists($path)) {
            return;
        }

        unlink($path);
    }
}

<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Feature;

use Override;
use Swoole\Coroutine;
use SwooleBundle\SwooleBundle\Client\HttpClient;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Command\TaskWorkerHeartbeatCommand;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Test\ServerTestCase;

/**
 * EXPERIMENTAL feature - long running console commands inside task workers.
 *
 * @see docs/swoole-task-worker-commands.md
 */
final class TaskWorkerCommandsTest extends ServerTestCase
{
    private const array COROUTINE_SLOTS = ['solo', 'shared-a', 'shared-b'];

    private const string BLOCKING_SLOT = 'blocking';

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->deleteVarDirectory();
        $this->deleteHeartbeatFiles();
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->deleteHeartbeatFiles();

        parent::tearDown();
    }

    public function testCommandsRunInTaskWorkersAndStopGracefullyWithCoroutines(): void
    {
        $serverRun = $this->createConsoleProcess([
            'swoole:server:run',
            '--host=localhost',
            sprintf('--port=%d', self::port()),
        ], ['APP_ENV' => 'task_worker_commands']);

        $serverRun->setTimeout(30);
        $serverRun->start();

        $this->runAsCoroutineAndWait(function (): void {
            $client = HttpClient::fromDomain('localhost', self::port(), false);
            $this->assertTrue($client->connect(self::connectTimeout(), 1, true));

            Coroutine::sleep(2);
        });

        foreach (self::COROUTINE_SLOTS as $slot) {
            $path = TaskWorkerHeartbeatCommand::filePath($slot);

            self::assertFileExists($path, sprintf('Command "%s" never started in a task worker.', $slot));
            self::assertStringContainsString(
                'tick',
                (string) file_get_contents($path),
                sprintf('Command "%s" started but never ran its loop.', $slot),
            );
        }

        // The two commands sharing a task worker must be in the same process, and the one on its own
        // must not be - which is what "a group is a task worker" means in configuration.
        $shared = [$this->pidOf('shared-a'), $this->pidOf('shared-b')];
        self::assertSame($shared[0], $shared[1], 'Commands in one group must share a task worker process.');
        self::assertNotSame(
            $this->pidOf('solo'),
            $shared[0],
            'Commands in separate groups must run in separate task worker processes.',
        );

        $serverRun->stop();

        foreach (self::COROUTINE_SLOTS as $slot) {
            self::assertStringContainsString(
                'stopped',
                (string) file_get_contents(TaskWorkerHeartbeatCommand::filePath($slot)),
                sprintf(
                    'Command "%s" was force-terminated rather than asked to stop - the stop signal did '
                    . 'not reach it.',
                    $slot,
                ),
            );
        }
    }

    public function testSingleCommandRunsInATaskWorkerWithCoroutinesDisabled(): void
    {
        $serverRun = $this->createConsoleProcess([
            'swoole:server:run',
            '--host=localhost',
            sprintf('--port=%d', self::port()),
        ], ['APP_ENV' => 'task_worker_commands_blocking']);

        $serverRun->setTimeout(30);
        $serverRun->start();

        $this->runAsCoroutineAndWait(function (): void {
            $client = HttpClient::fromDomain('localhost', self::port(), false);
            $this->assertTrue($client->connect(self::connectTimeout(), 1, true));

            Coroutine::sleep(2);
        });

        $path = TaskWorkerHeartbeatCommand::filePath(self::BLOCKING_SLOT);

        self::assertFileExists($path, 'Command never started in the task worker.');
        self::assertStringContainsString(
            'tick',
            (string) file_get_contents($path),
            'Command started but never ran its loop.',
        );

        // Deliberately no assertion on a graceful stop. With coroutines off there is no watchdog to hand
        // the command a signal, and this command has no cooperative check of its own, so it is
        // force-terminated - which is exactly what the docs say happens.
        $serverRun->stop();
    }

    private function pidOf(string $slot): string
    {
        $contents = (string) file_get_contents(TaskWorkerHeartbeatCommand::filePath($slot));

        self::assertSame(1, preg_match('/^started pid=(\d+)$/m', $contents, $matches));

        return $matches[1];
    }

    private function deleteHeartbeatFiles(): void
    {
        $slots = [...self::COROUTINE_SLOTS, self::BLOCKING_SLOT];

        foreach ($slots as $slot) {
            $path = TaskWorkerHeartbeatCommand::filePath($slot);

            if (!file_exists($path)) {
                continue;
            }

            unlink($path);
        }
    }
}

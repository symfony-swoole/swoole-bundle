<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Feature;

use Override;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Command\TaskWorkerHeartbeatCommand;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Test\ServerTestCase;

/**
 * EXPERIMENTAL feature - a server refuses to start with worker_max_request and blocking task worker
 * commands, even when the number comes from an env var the container could not see.
 *
 * @see docs/swoole-task-worker-commands.md
 */
final class TaskWorkerBlockingMaxRequestRefusedTest extends ServerTestCase
{
    private const string SLOT = 'blocking-max-request';

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

    public function testTheServerRefusesToStart(): void
    {
        $serverRun = $this->createConsoleProcess([
            'swoole:server:run',
            '--host=localhost',
            sprintf('--port=%d', self::port()),
        ], ['APP_ENV' => 'task_worker_commands_blocking_max_request']);

        $serverRun->setTimeout(30);
        $serverRun->run();

        $output = $serverRun->getOutput() . $serverRun->getErrorOutput();
        // Collapsed, because the console wraps its error block to the terminal width.
        $flattened = preg_replace('/\s+/', ' ', $output) ?? $output;

        self::assertNotSame(0, $serverRun->getExitCode(), sprintf(
            'The server started, so the first recycle of its http worker would have stopped the command for '
            . "good. Output:\n%s",
            $output,
        ));
        self::assertStringContainsString('worker_max_request is 50', $flattened, sprintf(
            "The server did not say why it refused to start. Output:\n%s",
            $output,
        ));
        // Refused before anything forked, not after the command had already been started.
        self::assertFileDoesNotExist(TaskWorkerHeartbeatCommand::filePath(self::SLOT));
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

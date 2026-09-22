<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Feature;

use Override;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Test\ServerTestCase;

/**
 * EXPERIMENTAL feature - what happens when a task worker's commands cannot run at all.
 *
 * A group that ends as fast as it starts is the one ending a recycle cannot answer: the replacement
 * would be forked into the same thing, and the worker would be replaced for as long as the server ran.
 * So the server stops instead, and the process running it exits non-zero.
 *
 * Which is the whole point of this test. A server that stays up with no commands running is a container
 * that passes every liveness probe while its queues go unread - the failure nobody is told about. The
 * exit code is what tells Kubernetes, systemd or a compose restart policy that this one is not working,
 * and it has to be distinguishable from the zero a stop that was asked for returns.
 *
 * @see \SwooleBundle\SwooleBundle\Bridge\Symfony\TaskWorker\TaskWorkerFailure
 */
final class TaskWorkerCommandsUnrunnableTest extends ServerTestCase
{
    private const string ENVIRONMENT = 'task_worker_commands_broken';

    private const string BLOCKING_ENVIRONMENT = 'task_worker_commands_broken_blocking';

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->deleteVarDirectory();
    }

    public function testTheServerStopsWithAFailingExitCode(): void
    {
        $this->assertServerFails(self::ENVIRONMENT);
    }

    /**
     * The blocking shape reaches the same place by a different road: no coroutine to spawn into, so the
     * command runs in onWorkerStart and recycle() is called by the worker itself rather than by a
     * coroutine waiting on the group.
     */
    public function testTheServerStopsWithAFailingExitCodeWithoutCoroutines(): void
    {
        $this->assertServerFails(self::BLOCKING_ENVIRONMENT);
    }

    private function assertServerFails(string $environment): void
    {
        $serverRun = $this->createConsoleProcess([
            'swoole:server:run',
            '--host=localhost',
            sprintf('--port=%d', self::port()),
        ], ['APP_ENV' => $environment]);

        $serverRun->setTimeout(30);
        $serverRun->run();

        $output = $serverRun->getOutput() . $serverRun->getErrorOutput();
        // Collapsed, because the console wraps its error block to the terminal width and the sentence
        // asserted below arrives split across two lines.
        $flattened = preg_replace('/\s+/', ' ', $output) ?? $output;

        // Nothing asserts that the process ended: run() is what waited for it, and a server left up
        // would have been the timeout instead.
        self::assertNotSame(0, $serverRun->getExitCode(), sprintf(
            'The server exited 0, which is what a stop somebody asked for returns - nothing supervising '
            . 'this container could tell the two apart. %s',
            $output,
        ));
        // The reason the workers logged goes wherever monolog sends it; what the process itself says is
        // this line, which is the one a `docker logs` after the restart is read for.
        self::assertStringContainsString('task worker could not run its commands', $flattened, sprintf(
            'The server went down without saying why, so whoever reads the output after the restart has '
            . 'nothing to go on. %s',
            $output,
        ));
    }
}

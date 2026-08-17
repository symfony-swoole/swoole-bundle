<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Feature;

use Override;
use Swoole\Coroutine;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Command\TaskWorkerHeartbeatCommand;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Test\ServerTestCase;

/**
 * EXPERIMENTAL feature - one deployable that answers liveness probes while running several worker
 * loops, which is the shape a supervisor-per-consumer deployment cannot express.
 *
 * @see docs/swoole-task-worker-commands.md
 */
final class TaskWorkerUnitTest extends ServerTestCase
{
    private const array SLOTS = ['unit-a', 'unit-b'];

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->deleteVarDirectory();
        $this->killAllProcessesListeningOnPort(self::port(2));
        $this->deleteHeartbeatFiles();
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->deleteHeartbeatFiles();

        parent::tearDown();
    }

    public function testHealthEndpointAnswersWhileWorkerLoopsRun(): void
    {
        $serverRun = $this->createConsoleProcess([
            'swoole:server:run',
            '--host=localhost',
            sprintf('--port=%d', self::port()),
        ], [
            'APP_ENV' => 'task_worker_unit',
            'HEALTH_PORT' => (string) self::port(2),
        ]);

        $serverRun->setTimeout(30);
        $serverRun->start();

        $this->runAsCoroutineAndWait(function (): void {
            $this->assertTrue($this->awaitHealthEndpoint(self::port(2)));

            // Let the loops get going, so the probe below is answered by a server whose task worker is
            // genuinely busy rather than one that has not started its commands yet.
            Coroutine::sleep(2);

            $startedAt = microtime(true);
            $response = $this->sendHealthRequest(self::port(2), '/healthz');
            $elapsed = microtime(true) - $startedAt;

            $this->assertSame(200, $response['statusCode']);
            $this->assertSame(['ok' => true], $response['body']);
            $this->assertLessThan(
                1.0,
                $elapsed,
                'The liveness probe must not queue behind the running worker loops.',
            );
        });

        foreach (self::SLOTS as $slot) {
            $path = TaskWorkerHeartbeatCommand::filePath($slot);

            self::assertFileExists($path, sprintf('Worker loop "%s" never started.', $slot));
            self::assertStringContainsString(
                'tick',
                (string) file_get_contents($path),
                sprintf('Worker loop "%s" started but never ran.', $slot),
            );
        }

        $serverRun->stop();

        foreach (self::SLOTS as $slot) {
            self::assertStringContainsString(
                'stopped',
                (string) file_get_contents(TaskWorkerHeartbeatCommand::filePath($slot)),
                sprintf('Worker loop "%s" was force-terminated rather than asked to stop.', $slot),
            );
        }
    }

    private function deleteHeartbeatFiles(): void
    {
        foreach (self::SLOTS as $slot) {
            $path = TaskWorkerHeartbeatCommand::filePath($slot);

            if (!file_exists($path)) {
                continue;
            }

            unlink($path);
        }
    }
}

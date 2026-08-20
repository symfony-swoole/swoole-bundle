<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Feature;

use Override;
use Swoole\Coroutine;
use SwooleBundle\SwooleBundle\Client\HttpClient;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Command\TaskWorkerHeartbeatCommand;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Test\ServerTestCase;
use SwooleBundle\SwooleBundle\Tests\Helper\TestToken;

/**
 * EXPERIMENTAL feature - a task worker's commands have to come back when the worker is replaced.
 *
 * Task workers are replaced in two ways, and both used to leave the server running with no commands in
 * it at all. The stop signal is shared memory that any worker raises on its way out and nothing lowers
 * again, so the replacement read a stop raised by the worker it replaced and stopped the commands it
 * had just started - for good, since nothing would lower the flag for the rest of the server's life.
 *
 * What the two cases exercise:
 *
 *  - reload: every worker is replaced at once, so the stop is raised by workers of the generation being
 *    replaced, and WorkerStopSignal's generation is what keeps it off the replacements.
 *  - recycle: one worker is replaced because its command ended, so the stop is raised by a worker in
 *    the same generation as its own replacement - which no generation can tell apart, and
 *    WorkerRetirement is what does.
 *
 * Neither asserts on the heartbeat file merely existing. A command stopped the moment it started leaves
 * one behind too, with a "started" line in it, so what separates the two is only whether anything is
 * still writing: ticks that go on accumulating for the command that survives a reload, and a succession
 * of distinct pids for the one whose worker is recycled over and over.
 *
 * @see docs/swoole-task-worker-commands.md
 * @see WorkerStopSignal
 * @see WorkerRetirement
 */
final class TaskWorkerCommandsSurviveWorkerReplacementTest extends ServerTestCase
{
    private const string RELOAD_SLOT = 'reload';

    private const string RECYCLE_SLOT = 'recycle';

    /**
     * Longer than the three seconds a plain server is given. Both environments compile the container
     * from cold - setUp deletes the cache - and then boot a task worker that forks a console command
     * before the server answers, which does not reliably fit in three seconds on a loaded build box.
     */
    private const int BOOT_TIMEOUT_SECONDS = 10;

    /**
     * SIGUSR1, which is what swoole's manager takes as "reload every worker" - the same thing HMR and
     * `swoole:server:reload` ask for. Spelled out because pcntl need not be loaded to send it.
     */
    private const int SIGNAL_RELOAD = 10;

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

    public function testCommandsKeepRunningAfterTheServerIsReloaded(): void
    {
        $serverRun = $this->createConsoleProcess([
            'swoole:server:run',
            '--host=localhost',
            sprintf('--port=%d', self::port()),
        ], ['APP_ENV' => 'task_worker_commands_reload']);

        $serverRun->setTimeout(60);
        $serverRun->start();

        $this->runAsCoroutineAndWait(function (): void {
            $client = HttpClient::fromDomain('localhost', self::port(), false);
            $this->assertTrue($client->connect(self::connectTimeout(self::BOOT_TIMEOUT_SECONDS), 1, true));

            Coroutine::sleep(2);
        });

        $pidBefore = $this->pidOf(self::RELOAD_SLOT);

        $serverRun->signal(self::SIGNAL_RELOAD);

        // Long enough for the workers being replaced to exit and raise their stop, so that a signal
        // leaking across generations has happened by the time the growth below is measured.
        self::sleep(4);

        $pidAfter = $this->pidOf(self::RELOAD_SLOT);
        self::assertNotSame(
            $pidBefore,
            $pidAfter,
            'The reload never replaced the task worker, so nothing about surviving one was tested.',
        );

        self::assertTicksKeepGrowing(
            self::RELOAD_SLOT,
            'The command restarted after the reload and was then stopped: the workers the reload '
            . 'replaced raised a stop that reached their replacements.',
        );

        $serverRun->stop();
    }

    public function testCommandsKeepRunningAfterItsWorkerIsRecycled(): void
    {
        $serverRun = $this->createConsoleProcess([
            'swoole:server:run',
            '--host=localhost',
            sprintf('--port=%d', self::port()),
        ], ['APP_ENV' => 'task_worker_commands_recycle']);

        $serverRun->setTimeout(60);
        $serverRun->start();

        $this->runAsCoroutineAndWait(function (): void {
            $client = HttpClient::fromDomain('localhost', self::port(), false);
            $this->assertTrue($client->connect(self::connectTimeout(self::BOOT_TIMEOUT_SECONDS), 1, true));

            Coroutine::sleep(1);
        });

        // The command ends by itself two seconds in, so left alone the worker is recycled over and over
        // and every replacement runs the command again. Counting how many distinct processes are seen
        // doing that is what tells a chain of replacements from a chain that stopped: a stop leaking
        // into the replacement ends it at the first one, which still leaves two pids behind.
        $pids = [];

        for ($sample = 0; $sample < 24; $sample++) {
            $pids[$this->pidOf(self::RECYCLE_SLOT)] = true;
            self::sleep(0.5);
        }

        self::assertGreaterThanOrEqual(
            3,
            count($pids),
            'The task worker stopped being replaced after ' . count($pids) . ' process(es): the worker '
            . 'being recycled raised a stop that reached its own replacement, so the replacement ended '
            . 'the command instead of running it.',
        );

        $serverRun->stop();
    }

    private static function assertTicksKeepGrowing(string $slot, string $message): void
    {
        $before = self::tickCount($slot);
        self::sleep(1);
        $after = self::tickCount($slot);

        self::assertGreaterThan($before, $after, $message);
    }

    private static function tickCount(string $slot): int
    {
        $contents = (string) file_get_contents(TaskWorkerHeartbeatCommand::filePath($slot));

        return substr_count($contents, "tick\n");
    }

    /**
     * @param float $seconds scaled by the parallel suite's timeout factor, as every other wait here is
     */
    private static function sleep(float $seconds): void
    {
        usleep((int) ($seconds * TestToken::timeoutFactor() * 1_000_000));
    }

    private function pidOf(string $slot): string
    {
        $path = TaskWorkerHeartbeatCommand::filePath($slot);

        self::assertFileExists($path, sprintf('Command "%s" never started in a task worker.', $slot));

        $contents = (string) file_get_contents($path);
        self::assertSame(1, preg_match('/^started pid=(\d+)$/m', $contents, $matches));

        return $matches[1];
    }

    private function deleteHeartbeatFiles(): void
    {
        foreach ([self::RELOAD_SLOT, self::RECYCLE_SLOT] as $slot) {
            $path = TaskWorkerHeartbeatCommand::filePath($slot);

            if (!file_exists($path)) {
                continue;
            }

            unlink($path);
        }
    }
}

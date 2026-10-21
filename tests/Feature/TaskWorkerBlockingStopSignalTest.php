<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Feature;

use Override;
use Swoole\Coroutine;
use SwooleBundle\SwooleBundle\Client\HttpClient;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Test\ServerTestCase;

/**
 * EXPERIMENTAL feature - a messenger consumer stops itself when coroutines are off.
 *
 * There is no watchdog coroutine in that mode - onWorkerStart runs outside any coroutine, so the
 * runner never spawns one - which leaves StopMessengerWorkerOnShutdown, on messenger's own
 * WorkerRunningEvent, as the only thing inside a running command that reads the shared stop flag.
 * Break the raise and this is what notices.
 *
 * Told apart by how long shutdown takes: a worker that stops itself goes quickly, one that has to be
 * force-terminated drags shutdown out to worker_max_wait_time, which the fixture sets to 8s.
 */
final class TaskWorkerBlockingStopSignalTest extends ServerTestCase
{
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->deleteVarDirectory();
    }

    public function testMessengerConsumerStopsItselfWithCoroutinesOff(): void
    {
        $serverRun = $this->createConsoleProcess([
            'swoole:server:run',
            '--host=localhost',
            sprintf('--port=%d', self::port()),
            // Debug off on purpose. In debug, HMR puts a repeating timer in the http worker, which keeps
            // its reactor from emptying and makes it fire WorkerExited as well as WorkerStopped - so the
            // raise would land even if the WorkerStopped subscription were removed, and this test would
            // pass while blocking-mode shutdown was broken in production.
        ], ['APP_ENV' => 'task_worker_blocking_messenger', 'APP_DEBUG' => '0']);

        $serverRun->setTimeout(40);
        $serverRun->start();

        $this->runAsCoroutineAndWait(function (): void {
            $client = HttpClient::fromDomain('localhost', self::port(), false);
            $this->assertTrue($client->connect(self::connectTimeout(), 1, true));

            Coroutine::sleep(2);
        });

        $startedAt = microtime(true);
        $serverRun->stop();
        $elapsed = microtime(true) - $startedAt;

        $output = $serverRun->getOutput() . $serverRun->getErrorOutput();

        self::assertStringNotContainsString(
            'forcibly terminated',
            $output,
            sprintf(
                'The consumer was force-terminated, so nothing read the stop flag. Shutdown took %.1fs. '
                . "Server output:\n%s",
                $elapsed,
                $output,
            ),
        );

        self::assertLessThan(
            7.0,
            $elapsed,
            sprintf('Shutdown ran to worker_max_wait_time (8s), which means the worker was killed, not stopped.'),
        );
    }
}

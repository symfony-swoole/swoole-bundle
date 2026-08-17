<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\TaskWorker;

use Override;
use Psr\Log\LoggerInterface;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\CoWrapper;
use SwooleBundle\SwooleBundle\Common\Adapter\Swoole;
use Throwable;

/**
 * EXPERIMENTAL. Runs one task worker's group of long running console commands.
 *
 * Two shapes, picked by whether coroutine support is on:
 *
 *  - coroutines on: each command gets a coroutine and onWorkerStart returns straight away, so the task
 *    worker still runs its reactor. It keeps serving onTask - the messenger task transport goes on
 *    working in the same process - and its own lifecycle callbacks still fire.
 *  - coroutines off: there is no scheduler to spawn into, so the single command blocks onWorkerStart and
 *    the task worker is dedicated to it. It serves no tasks and reaches none of its own callbacks until
 *    the command returns.
 *
 * Recycling is the point of the bookkeeping. A command that returns on --memory-limit has done its job
 * only if the process it leaked into goes away, so the worker is stopped and swoole's manager forks a
 * replacement, which runs onWorkerStart again and starts the command afresh. Stopping the worker is
 * Server::stop() and not exit(): exit() inside a coroutine raises Swoole\ExitException and the manager
 * logs the replacement as "abnormal exit, status=255", where Server::stop() recycles silently.
 */
final readonly class CommandGroupRunner implements CommandGroupExecutor
{
    /**
     * Below this, a group that ended is treated as broken rather than as finished work.
     *
     * Without it a command that cannot start - a typo in the command line is enough - would end
     * instantly, get its worker recycled, and be forked again in a loop for as long as the server runs.
     */
    private const float MINIMUM_RUNTIME_SECONDS = 1.0;

    public function __construct(
        private CommandResolver $resolver,
        private CommandOutputFactory $outputFactory,
        private WorkerStopSignal $stopSignal,
        private Swoole $swoole,
        private LoggerInterface $logger,
        private int $stopPollIntervalMs = 100,
    ) {}

    /**
     * @param list<string> $commandLines
     */
    #[Override]
    public function runInCoroutines(WorkerControl $control, int $workerId, array $commandLines): void
    {
        $state = new StopState();
        $waitGroup = $this->swoole->waitGroup(count($commandLines));
        $startedAt = microtime(true);

        foreach ($commandLines as $commandLine) {
            CoWrapper::go(function () use ($commandLine, $state, $waitGroup): void {
                try {
                    $this->runSupervised($commandLine, $state);
                } finally {
                    // In the finally rather than after the call: a throwable escaping runSupervised
                    // unwinds inside this coroutine and reaches nobody, and a group that never drains
                    // is a worker hung on a command that already stopped running.
                    $waitGroup->done();
                }
            });
        }

        CoWrapper::go(function () use ($control, $workerId, $waitGroup, $startedAt): void {
            $waitGroup->wait();
            $this->recycle($control, $workerId, $startedAt);
        });
    }

    #[Override]
    public function runBlocking(WorkerControl $control, int $workerId, string $commandLine): void
    {
        $startedAt = microtime(true);
        $this->runSupervised($commandLine, new StopState());
        $this->recycle($control, $workerId, $startedAt);
    }

    /**
     * Stopping the worker rather than returning is not optional in the blocking case. A task worker that
     * returns from a blocking onWorkerStart is past its own shutdown path: it drains whatever queued up
     * on its pipe and then sits there until the manager gives up and force-terminates the lot
     * ("wait timeout, all worker processes will be forcibly terminated"), turning a 4 second shutdown
     * into a 14 second one.
     */
    private function recycle(WorkerControl $control, int $workerId, float $startedAt): void
    {
        if ($this->stopSignal->isRaised()) {
            // The server is going down; the manager is already taking this worker with it.
            return;
        }

        $runtime = microtime(true) - $startedAt;

        if ($runtime < self::MINIMUM_RUNTIME_SECONDS) {
            $this->logger->critical(
                'Task worker {workerId} commands ended after {runtime}s, which is too fast to be real '
                . 'work - not recycling the worker, because forking it again would only repeat this. '
                . 'Check the configured command lines.',
                ['workerId' => $workerId, 'runtime' => round($runtime, 3)],
            );

            return;
        }

        $this->logger->info(
            'Task worker {workerId} commands finished after {runtime}s, recycling the worker.',
            ['workerId' => $workerId, 'runtime' => round($runtime, 3)],
        );

        $control->stop($workerId);
    }

    private function runSupervised(string $commandLine, StopState $state): void
    {
        try {
            $resolved = $this->resolver->resolve($commandLine);
        } catch (Throwable $throwable) {
            $this->logger->critical(
                'Task worker command "{command}" could not be started: {message}',
                ['command' => $commandLine, 'message' => $throwable->getMessage(), 'exception' => $throwable],
            );

            return;
        }

        $running = new RunningState();

        if ($this->swoole->getCoroutineId() > 0) {
            $this->watch($resolved, $state, $running);
        }

        try {
            $exitCode = $resolved->run($this->outputFactory->newOutput($commandLine));

            $this->logger->info(
                'Task worker command "{command}" returned {exitCode}.',
                ['command' => $commandLine, 'exitCode' => $exitCode],
            );
        } catch (Throwable $throwable) {
            $this->logger->error(
                'Task worker command "{command}" failed: {message}',
                ['command' => $commandLine, 'message' => $throwable->getMessage(), 'exception' => $throwable],
            );
        } finally {
            $running->markFinished();
            $state->request();
        }
    }

    /**
     * Polls for a stop and hands it to the command, standing in for the signal handler that cannot work
     * here. 100ms is well inside any shutdown budget and costs nothing while idle.
     *
     * @see RunningState for how the watchdog learns the command returned on its own and stops polling
     */
    private function watch(ResolvedCommand $resolved, StopState $state, RunningState $running): void
    {
        CoWrapper::go(function () use ($resolved, $state, $running): void {
            while ($running->isRunning()) {
                if ($state->isRequested() || $this->stopSignal->isRaised()) {
                    $this->deliver($resolved);

                    return;
                }

                usleep($this->stopPollIntervalMs * 1000);
            }
        });
    }

    private function deliver(ResolvedCommand $resolved): void
    {
        if ($resolved->requestStop()) {
            return;
        }

        // Nothing to aim at: the command subscribes to no signals, so the only thing left that can stop
        // it is a cooperative check of its own. StopMessengerWorkerOnShutdown is that check for
        // messenger; anything else has to bring its own or be force-terminated at max_wait_time.
        $this->logger->warning(
            'Task worker command "{command}" subscribes to no signals, so it cannot be asked to stop. '
            . 'It will be force-terminated when max_wait_time expires unless it stops itself.',
            ['command' => $resolved->commandLine],
        );
    }
}

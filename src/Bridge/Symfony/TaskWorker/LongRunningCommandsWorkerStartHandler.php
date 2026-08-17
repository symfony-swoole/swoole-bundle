<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\TaskWorker;

use Override;
use Swoole\Server;
use SwooleBundle\SwooleBundle\Server\WorkerHandler\WorkerStartHandler;

/**
 * EXPERIMENTAL. Starts the configured long running commands when a task worker boots.
 *
 * onWorkerStart is the hook because it is the one a respawned worker runs again: when a command ends on
 * --memory-limit and its worker is recycled, the replacement starts the command by itself with nothing
 * needing to notice or re-dispatch anything.
 *
 * Http workers fall straight through - only task workers run commands.
 */
final readonly class LongRunningCommandsWorkerStartHandler implements WorkerStartHandler
{
    public function __construct(
        private TaskWorkerCommands $commands,
        private CommandGroupExecutor $runner,
        private bool $coroutinesEnabled,
        private ?WorkerStartHandler $decorated = null,
    ) {}

    #[Override]
    public function handle(Server $server, int $workerId): void
    {
        // First, and before anything blocks: with coroutines off the call below never returns, and a
        // decorated handler left until afterwards would simply never run in a task worker.
        if ($this->decorated instanceof WorkerStartHandler) {
            $this->decorated->handle($server, $workerId);
        }

        if (!$server->taskworker) {
            return;
        }

        $commandLines = $this->commands->forTaskWorker($this->taskWorkerIndex($server, $workerId));

        if ($commandLines === []) {
            return;
        }

        $control = new SwooleWorkerControl($server);

        if ($this->coroutinesEnabled) {
            $this->runner->runInCoroutines($control, $workerId, $commandLines);

            return;
        }

        // Configuration rejects groups larger than one when coroutines are off, so this is the group.
        $this->runner->runBlocking($control, $workerId, $commandLines[0]);
    }

    /**
     * Swoole numbers task workers after the http workers, so with worker_num => 2 the first task worker
     * is worker id 2. Configuration counts groups from the first task worker, so shift the id back.
     */
    private function taskWorkerIndex(Server $server, int $workerId): int
    {
        /** @var array{worker_num?: int|string} $settings */
        $settings = $server->setting;

        return $workerId - (int) ($settings['worker_num'] ?? 1);
    }
}

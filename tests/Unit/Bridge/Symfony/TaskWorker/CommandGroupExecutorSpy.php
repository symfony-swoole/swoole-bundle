<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Symfony\TaskWorker;

use Override;
use SwooleBundle\SwooleBundle\Bridge\Symfony\TaskWorker\CommandGroupExecutor;
use SwooleBundle\SwooleBundle\Bridge\Symfony\TaskWorker\WorkerControl;

final class CommandGroupExecutorSpy implements CommandGroupExecutor
{
    /**
     * @var list<array{mode: string, workerId: int, commands: list<string>}>
     */
    private array $calls = [];

    /**
     * @return list<array{mode: string, workerId: int, commands: list<string>}>
     */
    public function calls(): array
    {
        return $this->calls;
    }

    /**
     * @return array{mode: string, workerId: int, commands: list<string>}
     */
    public function call(int $index): array
    {
        return $this->calls[$index];
    }

    /**
     * @param list<string> $commandLines
     */
    #[Override]
    public function runInCoroutines(WorkerControl $control, int $workerId, array $commandLines): void
    {
        $this->calls[] = ['mode' => 'coroutines', 'workerId' => $workerId, 'commands' => $commandLines];
    }

    #[Override]
    public function runBlocking(WorkerControl $control, int $workerId, string $commandLine): void
    {
        $this->calls[] = ['mode' => 'blocking', 'workerId' => $workerId, 'commands' => [$commandLine]];
    }
}

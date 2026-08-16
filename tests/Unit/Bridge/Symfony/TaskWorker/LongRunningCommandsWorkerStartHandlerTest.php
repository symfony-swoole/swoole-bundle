<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Symfony\TaskWorker;

use PHPUnit\Framework\TestCase;
use SwooleBundle\SwooleBundle\Bridge\Symfony\TaskWorker\LongRunningCommandsWorkerStartHandler;
use SwooleBundle\SwooleBundle\Bridge\Symfony\TaskWorker\TaskWorkerCommands;

final class LongRunningCommandsWorkerStartHandlerTest extends TestCase
{
    public function testThatHttpWorkersRunNoCommands(): void
    {
        $executor = new CommandGroupExecutorSpy();
        $handler = $this->handler($executor, [['messenger:consume default']]);

        $handler->handle(TaskWorkerServerMock::make(taskworker: false, workerCount: 2), 0);

        self::assertSame([], $executor->calls());
    }

    /**
     * Swoole numbers task workers after the http workers, so with worker_num => 2 the first task worker
     * is worker id 2 and has to be handed group 0. Getting this wrong is quiet: the commands simply
     * never start.
     */
    public function testThatTaskWorkerIdsAreShiftedOntoGroupIndexes(): void
    {
        $executor = new CommandGroupExecutorSpy();
        $handler = $this->handler($executor, [['first'], ['second']]);
        $server = TaskWorkerServerMock::make(taskworker: true, workerCount: 2);

        $handler->handle($server, 2);
        $handler->handle($server, 3);

        self::assertSame(['first'], $executor->call(0)['commands']);
        self::assertSame(['second'], $executor->call(1)['commands']);
    }

    public function testThatTaskWorkersBeyondTheConfiguredGroupsRunNothing(): void
    {
        $executor = new CommandGroupExecutorSpy();
        $handler = $this->handler($executor, [['only-group']]);
        $server = TaskWorkerServerMock::make(taskworker: true, workerCount: 1);

        // Task worker 1 (worker id 2) has no group: it stays an ordinary task worker serving tasks.
        $handler->handle($server, 2);

        self::assertSame([], $executor->calls());
    }

    public function testThatCoroutinesEnabledRunsTheWholeGroupTogether(): void
    {
        $executor = new CommandGroupExecutorSpy();
        $handler = $this->handler($executor, [['a', 'b']], coroutinesEnabled: true);

        $handler->handle(TaskWorkerServerMock::make(taskworker: true, workerCount: 1), 1);

        self::assertSame('coroutines', $executor->call(0)['mode']);
        self::assertSame(['a', 'b'], $executor->call(0)['commands']);
    }

    public function testThatCoroutinesDisabledRunsTheSingleCommandBlocking(): void
    {
        $executor = new CommandGroupExecutorSpy();
        $handler = $this->handler($executor, [['a']], coroutinesEnabled: false);

        $handler->handle(TaskWorkerServerMock::make(taskworker: true, workerCount: 1), 1);

        self::assertSame('blocking', $executor->call(0)['mode']);
        self::assertSame(['a'], $executor->call(0)['commands']);
    }

    /**
     * With coroutines off the run call never returns, so a decorated handler left until afterwards would
     * never run at all in a task worker.
     */
    public function testThatTheDecoratedHandlerRunsBeforeAnythingBlocks(): void
    {
        $decorated = new RecordingWorkerStartHandler();
        $executor = new CommandGroupExecutorSpy();

        $handler = new LongRunningCommandsWorkerStartHandler(
            new TaskWorkerCommands([['a']]),
            $executor,
            false,
            $decorated,
        );

        $handler->handle(TaskWorkerServerMock::make(taskworker: true, workerCount: 1), 1);

        self::assertSame([1], $decorated->handledWorkerIds());
        self::assertCount(1, $executor->calls());
    }

    /**
     * @param list<list<string>> $groups
     */
    private function handler(
        CommandGroupExecutorSpy $executor,
        array $groups,
        bool $coroutinesEnabled = true,
    ): LongRunningCommandsWorkerStartHandler {
        return new LongRunningCommandsWorkerStartHandler(
            new TaskWorkerCommands($groups),
            $executor,
            $coroutinesEnabled,
        );
    }
}

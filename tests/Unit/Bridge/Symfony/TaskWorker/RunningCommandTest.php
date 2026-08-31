<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Symfony\TaskWorker;

use PHPUnit\Framework\TestCase;
use SwooleBundle\SwooleBundle\Bridge\Symfony\TaskWorker\RunningCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;

final class RunningCommandTest extends TestCase
{
    public function testThatACoroutineIsToldWhatItIsRunning(): void
    {
        $swoole = (new CoroutineTreeSwoole())->spawn(1)->switchTo(1);
        $runningCommand = new RunningCommand($swoole);

        $runningCommand->record('messenger:consume default');

        self::assertSame('messenger:consume default', $runningCommand->current());
    }

    /**
     * The whole point of the coroutine context over a field: the commands of one task worker run side by
     * side in one process, and a field would be them overwriting each other.
     */
    public function testThatTheCommandsOfAGroupDoNotOverwriteEachOther(): void
    {
        $swoole = (new CoroutineTreeSwoole())->spawn(1)->spawn(2);
        $runningCommand = new RunningCommand($swoole);

        $swoole->switchTo(1);
        $runningCommand->record('tacticus:projection:run --group=projector');

        $swoole->switchTo(2);
        $runningCommand->record('tacticus:projection:run --group=open_banking');

        self::assertSame('tacticus:projection:run --group=open_banking', $runningCommand->current());

        $swoole->switchTo(1);
        self::assertSame('tacticus:projection:run --group=projector', $runningCommand->current());
    }

    /**
     * The case nearly all the work falls into: a consumer handles a message in a coroutine of its own,
     * and that coroutine inherits nothing - the command is found by walking up to the one running it.
     */
    public function testThatASpawnedCoroutineIsAttributedToTheCommandAboveIt(): void
    {
        $swoole = (new CoroutineTreeSwoole())->spawn(1)->spawn(2, parent: 1)->spawn(3, parent: 2);
        $runningCommand = new RunningCommand($swoole);

        $swoole->switchTo(1);
        $runningCommand->record('messenger:consume default');

        $swoole->switchTo(3);
        self::assertSame('messenger:consume default', $runningCommand->current());
    }

    /**
     * A walk that has to end somewhere, since a request has no command above it and looking for one must
     * not become the cost of every log line.
     */
    public function testThatACoroutineUnderNoCommandIsAttributedToNothing(): void
    {
        $swoole = (new CoroutineTreeSwoole())->spawn(1)->spawn(2, parent: 1)->switchTo(2);

        self::assertNull((new RunningCommand($swoole))->current());
    }

    /**
     * A chain longer than the walk is bounded to. It answers nothing rather than searching on, which is
     * the trade the bound is: a chain that somehow looped would otherwise take the worker with it.
     */
    public function testThatTheWalkStopsWellBeforeAnEndlessChain(): void
    {
        $swoole = new CoroutineTreeSwoole();

        for ($cid = 1; $cid <= 30; $cid++) {
            $swoole->spawn($cid, parent: $cid - 1);
        }

        $runningCommand = new RunningCommand($swoole);

        $swoole->switchTo(1);
        $runningCommand->record('messenger:consume default');

        $swoole->switchTo(30);
        self::assertNull($runningCommand->current());
    }

    /**
     * With coroutines off there is no context to write into, and a process outside a coroutine is
     * running exactly one command - so a field is both the only place left and a correct one.
     */
    public function testThatAProcessWithNoSchedulerRemembersItsOwnCommand(): void
    {
        $runningCommand = new RunningCommand(new CoroutineTreeSwoole());

        $runningCommand->record('messenger:consume default');

        self::assertSame('messenger:consume default', $runningCommand->current());
    }

    /**
     * A coroutine that was told nothing still falls back to what the process is running, because with
     * coroutines off the command is in the field and the work it spawns is not.
     */
    public function testThatACoroutineFallsBackToWhatTheProcessIsRunning(): void
    {
        $swoole = new CoroutineTreeSwoole();
        $runningCommand = new RunningCommand($swoole);

        $runningCommand->record('messenger:consume default');

        $swoole->spawn(1)->switchTo(1);
        self::assertSame('messenger:consume default', $runningCommand->current());
    }

    /**
     * Every worker of a server is forked from a master running swoole:server:run, and none of what a
     * worker goes on to do belongs to that command.
     */
    public function testThatTheCommandForkedFromIsForgotten(): void
    {
        $runningCommand = new RunningCommand(new CoroutineTreeSwoole());

        $runningCommand->record('swoole:server:run');
        $runningCommand->forget();

        self::assertNull($runningCommand->current());
    }

    public function testThatAConsoleCommandRecordsItsName(): void
    {
        $runningCommand = new RunningCommand(new CoroutineTreeSwoole());

        $runningCommand->recordConsoleCommand(self::consoleEventFor(new Command('messenger:consume')));

        self::assertSame('messenger:consume', $runningCommand->current());
    }

    /**
     * A console run with no command resolved - `bin/console` on its own - has nothing to record, and
     * must not wipe what a worker was told it is running.
     */
    public function testThatAConsoleEventWithoutACommandChangesNothing(): void
    {
        $runningCommand = new RunningCommand(new CoroutineTreeSwoole());

        $runningCommand->record('messenger:consume default');
        $runningCommand->recordConsoleCommand(self::consoleEventFor(null));

        self::assertSame('messenger:consume default', $runningCommand->current());
    }

    private static function consoleEventFor(?Command $command): ConsoleCommandEvent
    {
        return new ConsoleCommandEvent($command, new StringInput(''), new NullOutput());
    }
}

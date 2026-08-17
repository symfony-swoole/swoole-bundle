<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Symfony\TaskWorker;

use PHPUnit\Framework\TestCase;
use SwooleBundle\SwooleBundle\Bridge\Symfony\TaskWorker\TaskWorkerCommands;

final class TaskWorkerCommandsTest extends TestCase
{
    public function testThatEachGroupBelongsToItsOwnTaskWorker(): void
    {
        $commands = new TaskWorkerCommands([
            0 => ['messenger:consume default'],
            1 => ['messenger:consume a', 'messenger:consume b'],
        ]);

        self::assertSame(['messenger:consume default'], $commands->forTaskWorker(0));
        self::assertSame(['messenger:consume a', 'messenger:consume b'], $commands->forTaskWorker(1));
    }

    public function testThatTaskWorkersWithoutAGroupGetNothing(): void
    {
        $commands = new TaskWorkerCommands([0 => ['messenger:consume default']]);

        // Task workers beyond the configured groups are ordinary ones - they serve tasks and run no
        // commands - so asking for their group must be empty rather than out of range.
        self::assertSame([], $commands->forTaskWorker(1));
        self::assertSame([], $commands->forTaskWorker(99));
    }

    public function testThatItReportsHowManyTaskWorkersTheGroupsNeed(): void
    {
        self::assertSame(2, (new TaskWorkerCommands([['a'], ['b', 'c']]))->taskWorkersRequired());
        self::assertSame(0, (new TaskWorkerCommands([]))->taskWorkersRequired());
    }

    public function testThatItKnowsWhenNothingIsConfigured(): void
    {
        self::assertTrue((new TaskWorkerCommands([]))->isEmpty());
        self::assertFalse((new TaskWorkerCommands([['a']]))->isEmpty());
    }
}

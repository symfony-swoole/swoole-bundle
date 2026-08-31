<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Monolog;

use DateTimeImmutable;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;
use SwooleBundle\SwooleBundle\Bridge\Monolog\WorkerContextProcessor;
use SwooleBundle\SwooleBundle\Bridge\Monolog\WorkerIdentity;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Event\WorkerStartedEvent;
use SwooleBundle\SwooleBundle\Bridge\Symfony\TaskWorker\RunningCommand;
use SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Symfony\TaskWorker\CoroutineTreeSwoole;
use SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Symfony\TaskWorker\TaskWorkerServerMock;

final class WorkerContextProcessorTest extends TestCase
{
    private const int HTTP_WORKERS = 4;

    public function testThatARecordSaysWhichWorkerCoroutineAndCommandWroteIt(): void
    {
        $swoole = (new CoroutineTreeSwoole())->spawn(7)->switchTo(7);
        $runningCommand = new RunningCommand($swoole);
        $runningCommand->record('tacticus:activity:consume');

        $processed = self::processorFor($swoole, $runningCommand, startedAs: 5)(self::newRecord());

        self::assertSame(
            ['worker' => 'task-1', 'cid' => 7, 'command' => 'tacticus:activity:consume'],
            $processed->extra,
        );
    }

    /**
     * Outside a server and outside a coroutine there is nothing true to say, and three empty fields
     * explaining that would be on every line of every console run.
     */
    public function testThatNothingIsAddedWhenThereIsNothingToSay(): void
    {
        $swoole = new CoroutineTreeSwoole();
        $processor = new WorkerContextProcessor(new WorkerIdentity(), new RunningCommand($swoole), $swoole);

        self::assertSame([], $processor(self::newRecord())->extra);
    }

    /**
     * Processors run in a chain, and one that replaced the extra rather than adding to it would throw
     * away whatever the processors before it had put there.
     */
    public function testThatWhatOtherProcessorsAddedIsKept(): void
    {
        $swoole = (new CoroutineTreeSwoole())->spawn(7)->switchTo(7);
        $processor = self::processorFor($swoole, new RunningCommand($swoole), startedAs: 0);

        $processed = $processor(self::newRecord(['uid' => 'abc123']));

        self::assertSame(['uid' => 'abc123', 'worker' => 'web-0', 'cid' => 7], $processed->extra);
    }

    /**
     * @param array<mixed> $extra
     */
    private static function newRecord(array $extra = []): LogRecord
    {
        return new LogRecord(
            new DateTimeImmutable('2026-08-31 12:00:00'),
            'app',
            Level::Warning,
            'Something happened.',
            [],
            $extra,
        );
    }

    private static function processorFor(
        CoroutineTreeSwoole $swoole,
        RunningCommand $runningCommand,
        int $startedAs,
    ): WorkerContextProcessor {
        $worker = new WorkerIdentity();
        $worker->onWorkerStarted(
            new WorkerStartedEvent(TaskWorkerServerMock::make(false, self::HTTP_WORKERS), $startedAs),
        );

        return new WorkerContextProcessor($worker, $runningCommand, $swoole);
    }
}

<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Symfony\TaskWorker;

use PHPUnit\Framework\TestCase;
use SwooleBundle\SwooleBundle\Bridge\Symfony\TaskWorker\StopCheck;
use SwooleBundle\SwooleBundle\Bridge\Symfony\TaskWorker\StopState;
use SwooleBundle\SwooleBundle\Bridge\Symfony\TaskWorker\WorkerStopSignal;

final class StopCheckTest extends TestCase
{
    /**
     * The supervisor case: no task worker commands configured, so no stop signal exists. A command written
     * against the check has to run there unchanged, and its own signal handling does the stopping.
     */
    public function testThatItAnswersNoWithoutAStopSignal(): void
    {
        self::assertFalse((new StopCheck(new CoroutineTreeSwoole()))->shouldStop());
    }

    public function testThatItAnswersNoUntilTheServerIsStopping(): void
    {
        self::assertFalse((new StopCheck(new CoroutineTreeSwoole(), new WorkerStopSignal()))->shouldStop());
    }

    public function testThatItAnswersYesOnceTheServerIsStopping(): void
    {
        $signal = new WorkerStopSignal();
        $signal->raise();

        self::assertTrue((new StopCheck(new CoroutineTreeSwoole(), $signal))->shouldStop());
    }

    /**
     * Coroutines off: no coroutine to keep the group in, and one command per process to keep it for.
     */
    public function testThatAGroupBoundOutsideACoroutineIsHeardWhenItStops(): void
    {
        $check = new StopCheck((new CoroutineTreeSwoole())->switchTo(-1), new WorkerStopSignal());
        $state = new StopState();
        $check->bind($state);

        self::assertFalse($check->shouldStop());

        $state->request();

        self::assertTrue($check->shouldStop());
    }

    /**
     * A command that hands its work to a coroutine of its own and asks from there still belongs to its
     * group - the lookup walks up to the coroutine the command was started in.
     */
    public function testThatAGroupIsHeardFromACoroutineTheCommandSpawned(): void
    {
        $swoole = (new CoroutineTreeSwoole())->spawn(5)
            ->spawn(6, 5);
        $check = new StopCheck($swoole->switchTo(5), new WorkerStopSignal());
        $state = new StopState();
        $check->bind($state);
        $state->request();

        $swoole->switchTo(6);

        self::assertTrue($check->shouldStop());
    }

    /**
     * Groups in different task workers never share a process, but commands of the same group run side by
     * side; what matters is that one command's group is not read off another's coroutine.
     */
    public function testThatAnotherCoroutinesGroupIsNotHeard(): void
    {
        $swoole = (new CoroutineTreeSwoole())->spawn(5)
            ->spawn(7);
        $check = new StopCheck($swoole->switchTo(5), new WorkerStopSignal());
        $state = new StopState();
        $check->bind($state);
        $state->request();

        $swoole->switchTo(7);

        self::assertFalse($check->shouldStop());
    }
}

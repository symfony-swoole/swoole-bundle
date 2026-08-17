<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Symfony\TaskWorker;

use PHPUnit\Framework\TestCase;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Event\WorkerExitedEvent;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Event\WorkerStoppedEvent;
use SwooleBundle\SwooleBundle\Bridge\Symfony\TaskWorker\RaiseStopSignalOnWorkerShutdown;
use SwooleBundle\SwooleBundle\Bridge\Symfony\TaskWorker\StopMessengerWorkerOnShutdown;
use SwooleBundle\SwooleBundle\Bridge\Symfony\TaskWorker\WorkerStopSignal;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;
use Symfony\Component\Messenger\Worker;

final class StopSignalSubscribersTest extends TestCase
{
    public function testThatWorkerExitRaisesTheStopSignal(): void
    {
        $signal = new WorkerStopSignal();
        $subscriber = new RaiseStopSignalOnWorkerShutdown($signal);

        self::assertFalse($signal->isRaised());

        $subscriber->onWorkerShutdown();

        self::assertTrue($signal->isRaised());
    }

    /**
     * onWorkerExit fires more than once per shutdown, so raising has to survive being repeated.
     */
    public function testThatRaisingIsIdempotent(): void
    {
        $signal = new WorkerStopSignal();
        $subscriber = new RaiseStopSignalOnWorkerShutdown($signal);

        $subscriber->onWorkerShutdown();
        $subscriber->onWorkerShutdown();

        self::assertTrue($signal->isRaised());
    }

    /**
     * onWorkerExit is the one that matters: in a task worker running commands, onWorkerStop does not
     * fire until the coroutines have ended, which is the very thing the flag exists to bring about.
     */
    public function testThatItListensToWorkerExitAndNotOnlyWorkerStop(): void
    {
        $subscribed = RaiseStopSignalOnWorkerShutdown::getSubscribedEvents();

        self::assertArrayHasKey(WorkerExitedEvent::NAME, $subscribed);
        self::assertArrayHasKey(WorkerStoppedEvent::NAME, $subscribed);
    }

    public function testThatMessengerWorkerKeepsRunningUntilTheSignalIsRaised(): void
    {
        $signal = new WorkerStopSignal();
        $worker = $this->createMock(Worker::class);
        $worker->expects(self::never())
            ->method('stop');

        (new StopMessengerWorkerOnShutdown($signal))->onWorkerRunning(new WorkerRunningEvent($worker, false));
    }

    public function testThatMessengerWorkerIsStoppedOnceTheSignalIsRaised(): void
    {
        $signal = new WorkerStopSignal();
        $signal->raise();

        $worker = $this->createMock(Worker::class);
        $worker->expects(self::once())
            ->method('stop');

        (new StopMessengerWorkerOnShutdown($signal))->onWorkerRunning(new WorkerRunningEvent($worker, false));
    }

    public function testThatResetClearsTheSignal(): void
    {
        $signal = new WorkerStopSignal();
        $signal->raise();
        $signal->reset();

        self::assertFalse($signal->isRaised());
    }
}

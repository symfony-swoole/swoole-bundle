<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Symfony\TaskWorker;

use PHPUnit\Framework\TestCase;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Event\WorkerExitedEvent;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Event\WorkerStoppedEvent;
use SwooleBundle\SwooleBundle\Bridge\Symfony\TaskWorker\RaiseStopSignalOnTaskWorkerExit;
use SwooleBundle\SwooleBundle\Bridge\Symfony\TaskWorker\RaiseStopSignalOnWorkerShutdown;
use SwooleBundle\SwooleBundle\Bridge\Symfony\TaskWorker\StopCheck;
use SwooleBundle\SwooleBundle\Bridge\Symfony\TaskWorker\StopMessengerWorkerOnShutdown;
use SwooleBundle\SwooleBundle\Bridge\Symfony\TaskWorker\WorkerRetirement;
use SwooleBundle\SwooleBundle\Bridge\Symfony\TaskWorker\WorkerStopSignal;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;
use Symfony\Component\Messenger\Worker;

final class StopSignalSubscribersTest extends TestCase
{
    public function testThatWorkerExitRaisesTheStopSignal(): void
    {
        $signal = new WorkerStopSignal();
        $subscriber = new RaiseStopSignalOnWorkerShutdown($signal, new WorkerRetirement());

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
        $subscriber = new RaiseStopSignalOnWorkerShutdown($signal, new WorkerRetirement());

        $subscriber->onWorkerShutdown();
        $subscriber->onWorkerShutdown();

        self::assertTrue($signal->isRaised());
    }

    /**
     * The coroutines-off raiser hears both, because the http worker - the only one that can raise there -
     * fires onWorkerExit only while something is left in its reactor, and onWorkerStop only once it has
     * drained. Whichever comes first is the notice.
     */
    public function testThatTheBlockingModeRaiserListensToBothWorkerEvents(): void
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

        (new StopMessengerWorkerOnShutdown(new StopCheck(new CoroutineTreeSwoole(), $signal)))
            ->onWorkerRunning(new WorkerRunningEvent($worker, false));
    }

    public function testThatMessengerWorkerIsStoppedOnceTheSignalIsRaised(): void
    {
        $signal = new WorkerStopSignal();
        $signal->raise();

        $worker = $this->createMock(Worker::class);
        $worker->expects(self::once())
            ->method('stop');

        (new StopMessengerWorkerOnShutdown(new StopCheck(new CoroutineTreeSwoole(), $signal)))
            ->onWorkerRunning(new WorkerRunningEvent($worker, false));
    }

    /**
     * The coroutines-on raiser: a task worker running commands is back in its event loop while they run,
     * so its own exit is the notice, and it raises for itself.
     */
    public function testThatATaskWorkerExitRaisesTheStopSignalWithCoroutines(): void
    {
        $signal = new WorkerStopSignal();

        (new RaiseStopSignalOnTaskWorkerExit($signal, new WorkerRetirement()))
            ->onWorkerExited(new WorkerExitedEvent(TaskWorkerServerMock::make(taskworker: true), 1));

        self::assertTrue($signal->isRaised());
    }

    /**
     * The bug this raiser exists for. An http worker recycled by worker_max_request leaves through the same
     * callbacks as one going down with the server, and fires onWorkerExit as well whenever it has a timer -
     * HMR's in debug. Raising from it would stop every command for good while the server carried on.
     */
    public function testThatAnHttpWorkerExitRaisesNothingWithCoroutines(): void
    {
        $signal = new WorkerStopSignal();

        (new RaiseStopSignalOnTaskWorkerExit($signal, new WorkerRetirement()))
            ->onWorkerExited(new WorkerExitedEvent(TaskWorkerServerMock::make(taskworker: false), 0));

        self::assertFalse($signal->isRaised());
    }

    public function testThatARetiringTaskWorkerRaisesNothingWithCoroutines(): void
    {
        $signal = new WorkerStopSignal();
        $retirement = new WorkerRetirement();
        $retirement->retire();

        (new RaiseStopSignalOnTaskWorkerExit($signal, $retirement))
            ->onWorkerExited(new WorkerExitedEvent(TaskWorkerServerMock::make(taskworker: true), 1));

        self::assertFalse($signal->isRaised());
    }

    /**
     * Only the exit: a task worker's onWorkerStop does not fire until its coroutines have ended, which is
     * the very thing the flag exists to bring about.
     */
    public function testThatTheCoroutineModeRaiserListensToTheExitOnly(): void
    {
        self::assertSame(
            [WorkerExitedEvent::NAME => 'onWorkerExited'],
            RaiseStopSignalOnTaskWorkerExit::getSubscribedEvents(),
        );
    }

    /**
     * The exit of a worker that is retiring so it can be replaced must not raise anything: the
     * replacement is forked into the same generation, so a raise here would stop the commands it is
     * about to start.
     */
    public function testThatARetiringWorkerRaisesNothing(): void
    {
        $signal = new WorkerStopSignal();
        $retirement = new WorkerRetirement();
        $retirement->retire();

        (new RaiseStopSignalOnWorkerShutdown($signal, $retirement))->onWorkerShutdown();

        self::assertFalse($signal->isRaised());
    }

    /**
     * What a reload looks like from the signal's side: the workers being replaced raise it for the
     * generation they started in, and the manager has already opened the one their replacements start
     * in.
     */
    public function testThatAStopRaisedByAnEarlierGenerationDoesNotReachTheNextOne(): void
    {
        $signal = new WorkerStopSignal();
        $signal->enterGeneration();
        $signal->raise();

        self::assertTrue($signal->isRaised(), 'A worker must see the stop raised for its own generation.');

        // The manager, on BeforeReload, followed by a replacement binding itself to what it opened.
        $signal->newGeneration();
        $signal->enterGeneration();

        self::assertFalse($signal->isRaised());
    }

    /**
     * The other half of the same rule: a stop raised while every live worker is in one generation - a
     * real shutdown - has to reach all of them.
     */
    public function testThatAStopReachesEveryWorkerOfItsOwnGeneration(): void
    {
        $raiser = new WorkerStopSignal();
        $raiser->newGeneration();
        $raiser->enterGeneration();
        $raiser->raise();

        self::assertTrue($raiser->isRaised());
    }
}

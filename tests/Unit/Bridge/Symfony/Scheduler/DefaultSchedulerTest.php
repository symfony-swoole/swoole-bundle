<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Symfony\Scheduler;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Scheduler\DefaultScheduler;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Scheduler\Event\FailureEvent;
use Symfony\Component\Scheduler\Event\PostRunEvent;
use Symfony\Component\Scheduler\Event\PreRunEvent;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\Trigger\CallbackTrigger;
use Throwable;

/**
 * A schedule with no cache/lock (Schedule::stateful() never called) starts every checkpoint at
 * "now" with index -1 - so a trigger that returns the probed instant back unchanged on its first
 * call is due immediately, and returning null on the second (made when the generator advances
 * past the message it just yielded) keeps it from firing again or looping forever. That's enough
 * to make a due-exactly-once message deterministically, without waiting on real time or faking
 * the clock.
 */
#[Group('unit')]
final class DefaultSchedulerTest extends TestCase
{
    public function testRunDispatchesDueMessagesThroughTheBus(): void
    {
        $message = new stdClass();

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())
            ->method('dispatch')
            ->with($message)
            ->willReturn(new Envelope($message));

        $scheduler = new DefaultScheduler($bus, [self::dueOnceSchedule($message)]);

        $scheduler->run();
    }

    public function testRunDispatchesPreRunAndPostRunEventsWhenDispatcherIsSet(): void
    {
        $message = new stdClass();

        $bus = self::createStub(MessageBusInterface::class);
        $bus->method('dispatch')
            ->willReturn(new Envelope($message));

        $dispatcher = new EventDispatcher();
        $captured = new stdClass();
        $dispatcher->addListener(PreRunEvent::class, static function (PreRunEvent $event) use ($captured): void {
            $captured->preRunEvent = $event;
        });
        $dispatcher->addListener(PostRunEvent::class, static function (PostRunEvent $event) use ($captured): void {
            $captured->postRunEvent = $event;
        });

        $scheduler = new DefaultScheduler($bus, [self::dueOnceSchedule($message)], dispatcher: $dispatcher);

        $scheduler->run();

        self::assertInstanceOf(PreRunEvent::class, $captured->preRunEvent ?? null);
        self::assertSame($message, $captured->preRunEvent->getMessage());
        self::assertInstanceOf(PostRunEvent::class, $captured->postRunEvent ?? null);
        self::assertSame($message, $captured->postRunEvent->getMessage());
    }

    public function testRunSkipsDispatchWhenAPreRunListenerCancelsTheMessage(): void
    {
        $message = new stdClass();

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->never())
            ->method('dispatch');

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(PreRunEvent::class, static function (PreRunEvent $event): void {
            $event->shouldCancel(true);
        });
        $captured = new stdClass();
        $captured->postRunDispatched = false;
        $dispatcher->addListener(PostRunEvent::class, static function () use ($captured): void {
            $captured->postRunDispatched = true;
        });

        $scheduler = new DefaultScheduler($bus, [self::dueOnceSchedule($message)], dispatcher: $dispatcher);

        $scheduler->run();

        self::assertFalse($captured->postRunDispatched);
    }

    public function testRunDispatchesFailureEventAndRethrowsByDefault(): void
    {
        $message = new stdClass();
        $busException = new RuntimeException('bus failed');

        $bus = self::createStub(MessageBusInterface::class);
        $bus->method('dispatch')
            ->willThrowException($busException);

        $dispatcher = new EventDispatcher();
        $captured = new stdClass();
        $dispatcher->addListener(FailureEvent::class, static function (FailureEvent $event) use ($captured): void {
            $captured->error = $event->getError();
        });

        $scheduler = new DefaultScheduler($bus, [self::dueOnceSchedule($message)], dispatcher: $dispatcher);

        try {
            $scheduler->run();
            self::fail('Expected the bus exception to propagate.');
        } catch (Throwable $error) {
            self::assertSame($busException, $error);
        }

        self::assertSame($busException, $captured->error ?? null);
    }

    public function testRunSwallowsTheExceptionWhenAFailureListenerIgnoresIt(): void
    {
        $message = new stdClass();

        $bus = self::createStub(MessageBusInterface::class);
        $bus->method('dispatch')
            ->willThrowException(new RuntimeException('bus failed'));

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(FailureEvent::class, static function (FailureEvent $event): void {
            $event->shouldIgnore(true);
        });

        $scheduler = new DefaultScheduler($bus, [self::dueOnceSchedule($message)], dispatcher: $dispatcher);

        $scheduler->run();

        self::addToAssertionCount(1); // Reaching here without a thrown exception is the assertion.
    }

    private static function dueOnceSchedule(object $message): Schedule
    {
        $state = new stdClass();
        $state->fired = false;
        $trigger = new CallbackTrigger(static function (DateTimeImmutable $run) use ($state): ?DateTimeImmutable {
            if ($state->fired) {
                return null;
            }

            $state->fired = true;

            return $run;
        });

        return (new Schedule())->add(RecurringMessage::trigger($trigger, $message));
    }
}

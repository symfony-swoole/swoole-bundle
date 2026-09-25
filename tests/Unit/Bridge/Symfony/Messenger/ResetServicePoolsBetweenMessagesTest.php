<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Symfony\Messenger;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\ServicePool\ServicePoolContainer;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\ServicePool\ServicePoolEntry;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\SimpleResetter;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Messenger\ResetServicePoolsBetweenMessages;
use SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Symfony\Container\ServicePool\ResettableSpy;
use SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Symfony\Container\ServicePool\ServicePoolSpy;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Symfony\Component\Messenger\EventListener\SendFailedMessageForRetryListener;

#[CoversClass(ResetServicePoolsBetweenMessages::class)]
final class ResetServicePoolsBetweenMessagesTest extends TestCase
{
    public function testThePoolsAreResetBeforeAMessageIsHandled(): void
    {
        $pool = new ServicePoolSpy(assigned: new ResettableSpy());

        (new ResetServicePoolsBetweenMessages(new ServicePoolContainer([new ServicePoolEntry($pool)])))
            ->onMessageReceived(new WorkerMessageReceivedEvent(new Envelope(new stdClass()), 'default'));

        self::assertSame(1, $pool->discardUnstableAssignedCallCount());
        self::assertSame(0, $pool->releaseFromCoroutineCallCount());
    }

    /**
     * A failed message is sent for retry on the connection its handler left behind, so an instance that
     * went bad is dropped as soon as the message fails, not only before the next one. Nothing is reset
     * yet: the failure listeners, logging included, still need the failed message's state.
     */
    public function testAnUnstableInstanceIsDroppedAsSoonAsAMessageFailsButNothingIsReset(): void
    {
        $resettable = new ResettableSpy();
        $pool = new ServicePoolSpy(assigned: $resettable);

        (new ResetServicePoolsBetweenMessages(
            new ServicePoolContainer([new ServicePoolEntry($pool, new SimpleResetter('reset'))]),
        ))->onMessageFailed(
            new WorkerMessageFailedEvent(new Envelope(new stdClass()), 'default', new RuntimeException('failed')),
        );

        self::assertSame(1, $pool->discardUnstableAssignedCallCount());
        self::assertSame(0, $resettable->resetCallCount());
        self::assertSame(0, $pool->releaseFromCoroutineCallCount());
    }

    public function testTheFailedMessageIsResetForBeforeItIsSentForRetry(): void
    {
        $events = ResetServicePoolsBetweenMessages::getSubscribedEvents();
        [, $retry] = SendFailedMessageForRetryListener::getSubscribedEvents()[WorkerMessageFailedEvent::class];

        self::assertGreaterThan($retry, $events[WorkerMessageFailedEvent::class][1]);
    }
}

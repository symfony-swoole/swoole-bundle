<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Symfony\Container\ServicePool;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\ServicePool\ServicePoolContainer;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\ServicePool\ServicePoolEntry;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\SimpleResetter;

/**
 * The release cycle runs from a Co::defer() callback at the end of a coroutine, where nothing is left
 * to catch what it throws - a throwable escaping it is an uncaught fatal that kills the whole worker
 * and every request in flight on it.
 *
 * @see \SwooleBundle\SwooleBundle\Bridge\Symfony\Container\CoWrapper
 */
#[CoversClass(ServicePoolContainer::class)]
final class ServicePoolContainerTest extends TestCase
{
    public function testAFailingResetterDoesNotStopTheOtherServicesBeingReset(): void
    {
        $resettable = new ResettableSpy();
        $container = new ServicePoolContainer([
            new ServicePoolEntry(new ServicePoolSpy(assigned: new ResettableSpy()), new ThrowingResetter()),
            new ServicePoolEntry(new ServicePoolSpy(assigned: $resettable), new SimpleResetter('reset')),
        ]);

        $container->releaseFromCoroutine();

        self::assertSame(1, $resettable->resetCallCount());
    }

    /**
     * The part that matters most: a coroutine that dies holding its pool entries takes those instances
     * out of circulation for good, and enough of those exhaust the pool and hang the worker.
     */
    public function testAFailingResetterDoesNotStopAnythingBeingReleased(): void
    {
        $failing = new ServicePoolSpy(assigned: new ResettableSpy());
        $healthy = new ServicePoolSpy(assigned: new ResettableSpy());
        $container = new ServicePoolContainer([
            new ServicePoolEntry($failing, new ThrowingResetter()),
            new ServicePoolEntry($healthy, new SimpleResetter('reset')),
        ]);

        $container->releaseFromCoroutine();

        self::assertSame(1, $failing->releaseFromCoroutineCallCount());
        self::assertSame(1, $healthy->releaseFromCoroutineCallCount());
    }

    public function testAFailingReleaseDoesNotStopTheOtherPoolsBeingReleased(): void
    {
        $healthy = new ServicePoolSpy(assigned: new ResettableSpy());
        $container = new ServicePoolContainer([
            new ServicePoolEntry(new ThrowingServicePool()),
            new ServicePoolEntry($healthy),
        ]);

        $container->releaseFromCoroutine();

        self::assertSame(1, $healthy->releaseFromCoroutineCallCount());
    }

    public function testTheReleaseCycleNeverThrows(): void
    {
        $container = new ServicePoolContainer([
            new ServicePoolEntry(new ThrowingServicePool(), new ThrowingResetter()),
        ]);

        $container->releaseFromCoroutine();

        // reaching here at all is the assertion: anything thrown above is a fatal in production
        self::assertSame(1, $container->count());
    }

    /**
     * An entry whose coroutine never checked the service out has nothing to reset, and asking its
     * resetter to reset null would be a failure of this cycle's own making.
     */
    public function testAnUnassignedEntryIsSkippedRatherThanReset(): void
    {
        $pool = new ServicePoolSpy();
        $container = new ServicePoolContainer([new ServicePoolEntry($pool, new ThrowingResetter())]);

        $container->releaseFromCoroutine();

        self::assertSame(1, $pool->releaseFromCoroutineCallCount());
    }

    /**
     * A messenger worker stays in one coroutine across every message it handles, so the instances have
     * to keep their assignment - resetting is the whole point, releasing would hand them straight back
     * to the same coroutine.
     */
    public function testResettingInCoroutineResetsWithoutReleasing(): void
    {
        $resettable = new ResettableSpy();
        $pool = new ServicePoolSpy(assigned: $resettable);
        $container = new ServicePoolContainer([new ServicePoolEntry($pool, new SimpleResetter('reset'))]);

        $container->resetInCoroutine();

        self::assertSame(1, $resettable->resetCallCount());
        self::assertSame(1, $pool->discardUnstableAssignedCallCount());
        self::assertSame(0, $pool->releaseFromCoroutineCallCount());
    }

    /**
     * The half that resetting alone cannot do: a closed EntityManager cannot be reopened, only replaced,
     * so every pool has to be given the chance to drop an instance the stability checker rejects -
     * including the ones with no resetter of their own.
     */
    public function testEveryPoolIsAskedToDiscardAnUnstableInstance(): void
    {
        $withResetter = new ServicePoolSpy(assigned: new ResettableSpy());
        $withoutResetter = new ServicePoolSpy(assigned: new ResettableSpy());
        $container = new ServicePoolContainer([
            new ServicePoolEntry($withResetter, new SimpleResetter('reset')),
            new ServicePoolEntry($withoutResetter),
        ]);

        $container->resetInCoroutine();

        self::assertSame(1, $withResetter->discardUnstableAssignedCallCount());
        self::assertSame(1, $withoutResetter->discardUnstableAssignedCallCount());
    }

    public function testAFailingResetterDoesNotStopTheOtherServicesBeingResetInCoroutine(): void
    {
        $resettable = new ResettableSpy();
        $healthy = new ServicePoolSpy(assigned: $resettable);
        $container = new ServicePoolContainer([
            new ServicePoolEntry(new ServicePoolSpy(assigned: new ResettableSpy()), new ThrowingResetter()),
            new ServicePoolEntry($healthy, new SimpleResetter('reset')),
        ]);

        $container->resetInCoroutine();

        self::assertSame(1, $resettable->resetCallCount());
        self::assertSame(1, $healthy->discardUnstableAssignedCallCount());
    }

    /**
     * One service failing to reset must not cost the worker every message that follows it.
     */
    public function testTheResetCycleNeverThrows(): void
    {
        $container = new ServicePoolContainer([
            new ServicePoolEntry(new ThrowingServicePool(), new ThrowingResetter()),
        ]);

        $container->resetInCoroutine();

        // reaching here at all is the assertion
        self::assertSame(1, $container->count());
    }
}

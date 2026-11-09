<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Symfony\Container\ServicePool;

use ArrayObject;
use Closure;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Disposer;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\ServicePool\BaseServicePool;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\ServicePool\DiServicePool;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\StabilityChecker;
use SwooleBundle\SwooleBundle\Common\Adapter\CoroutineYielding;
use SwooleBundle\SwooleBundle\Common\Adapter\Swoole;
use SwooleBundle\SwooleBundle\Component\Locking\Mutex;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * How many of the instances given back a pool keeps, and what it does with the others.
 *
 * Each coroutine is told by the id the Swoole adapter answers, which a test sets before each step; a coroutine gives
 * its instance back as it ends, which is releaseFromCoroutine() with its id set.
 */
#[CoversClass(BaseServicePool::class)]
#[CoversClass(DiServicePool::class)]
final class BaseServicePoolIdleLimitTest extends TestCase
{
    private int $coroutineId = 1;

    /**
     * The coroutines still running, by id.
     *
     * @var array<int, true>
     */
    private array $running = [];

    /**
     * @var list<object>
     */
    private array $disposed = [];

    /**
     * How many times a pool let the other coroutines run.
     */
    private int $yields = 0;

    /**
     * What a pool always did: a burst of coroutines leaves as many instances behind as it had at once.
     */
    public function testWithoutAnIdleLimitEveryInstanceGivenBackIsKept(): void
    {
        $pool = $this->pool(idleLimit: null);
        $instances = $this->getInCoroutines($pool, 1, 2, 3);
        $this->releaseInCoroutines($pool, 1, 2, 3);

        self::assertSame([], $this->disposed);
        self::assertSameInstances($instances, $this->getInCoroutines($pool, 4, 5, 6));
    }

    public function testPastTheIdleLimitAnInstanceGivenBackIsDisposedOf(): void
    {
        $pool = $this->pool(idleLimit: 1);
        [$first, $second, $third] = $this->getInCoroutines($pool, 1, 2, 3);
        $this->releaseInCoroutines($pool, 1, 2, 3);

        self::assertSame([$second, $third], $this->disposed);
        [$reused, $built] = $this->getInCoroutines($pool, 4, 5);
        self::assertSame($first, $reused);
        self::assertNotContains($built, [$first, $second, $third]);
    }

    /**
     * Every coroutine is then given a new instance, and every instance is disposed of as its coroutine ends.
     */
    public function testAnIdleLimitOfNoneKeepsNothing(): void
    {
        $pool = $this->pool(idleLimit: 0);
        [$given] = $this->getInCoroutines($pool, 1);
        $this->releaseInCoroutines($pool, 1);

        self::assertSame([$given], $this->disposed);
        self::assertNotSame($given, $this->getInCoroutines($pool, 2)[0]);
    }

    /**
     * An instance the pool may keep is disposed of all the same where its stability checker rejects it - when its
     * coroutine gives it back, and when a coroutine that keeps it discards it.
     */
    public function testAnUnstableInstanceIsDisposedOfWhereverItIsDropped(): void
    {
        $pool = $this->pool(idleLimit: 10);
        [$givenBack, $discarded] = $this->getInCoroutines($pool, 1, 2);
        $givenBack->unstable = true;
        $discarded->unstable = true;

        $this->releaseInCoroutines($pool, 1);
        $this->coroutineId = 2;
        $pool->discardUnstableAssigned();

        self::assertSame([$givenBack, $discarded], $this->disposed);
        self::assertNull($pool->getAssigned());
    }

    public function testDrainingDisposesOfTheFreeInstancesAndLeavesTheAssignedOnes(): void
    {
        $pool = $this->pool(idleLimit: null);
        [$free, $assigned] = $this->getInCoroutines($pool, 1, 2);
        $this->releaseInCoroutines($pool, 1);

        $pool->drain();

        self::assertSame([$free], $this->disposed);
        $this->coroutineId = 2;
        self::assertSame($assigned, $pool->getAssigned());
    }

    /**
     * A coroutine gives its instances back before it has ended, and is not to share one with the next while it runs.
     */
    public function testAnInstanceWhoseCoroutineHasEndedIsTakenBeforeOneStillOnItsWayOut(): void
    {
        $pool = $this->pool(idleLimit: null);
        [, $ended] = $this->getInCoroutines($pool, 1, 2);
        $this->releaseInCoroutines($pool, 1, 2);
        $this->running = [1 => true];

        self::assertSameInstances([$ended], $this->getInCoroutines($pool, 3));
    }

    /**
     * Not a coroutine to wait for: the one asking is the one that gave it back. Waiting would mean waiting for itself,
     * which no runtime is here to do in a unit test - so a wait would show up as an error.
     */
    public function testACoroutineTakesBackWhatItGaveWithoutWaitingForItself(): void
    {
        $pool = $this->pool(idleLimit: null);
        [$given] = $this->getInCoroutines($pool, 1);
        $this->releaseInCoroutines($pool, 1);
        $this->running = [1 => true];

        self::assertSameInstances([$given], $this->getInCoroutines($pool, 1));
    }

    /**
     * Where the runtime can let the others run without a timer, the coroutine taking an instance does that until the
     * one that gave it back has ended - here two rounds, after which coroutine 1 is gone.
     */
    public function testACoroutineWaitingForOneOnItsWayOutYieldsUntilItIsGone(): void
    {
        $pool = $this->pool(idleLimit: null, onYield: function (): void {
            $this->yields++;

            if ($this->yields < 2) {
                return;
            }

            unset($this->running[1]);
        });
        [$given] = $this->getInCoroutines($pool, 1);
        $this->releaseInCoroutines($pool, 1);
        $this->running = [1 => true];

        self::assertSameInstances([$given], $this->getInCoroutines($pool, 2));
        self::assertSame(2, $this->yields);
    }

    /**
     * @param list<object> $expected
     * @param list<object> $actual
     */
    private static function assertSameInstances(array $expected, array $actual): void
    {
        self::assertSame(
            array_map(spl_object_id(...), $expected),
            array_map(spl_object_id(...), $actual),
        );
    }

    private function recordDisposal(object $service): void
    {
        $this->disposed[] = $service;
    }

    /**
     * @param DiServicePool<stdClass> $pool
     * @return list<stdClass>
     */
    private function getInCoroutines(DiServicePool $pool, int ...$coroutineIds): array
    {
        $instances = [];

        foreach ($coroutineIds as $coroutineId) {
            $this->coroutineId = $coroutineId;
            $instances[] = $pool->get();
        }

        return $instances;
    }

    /**
     * @param DiServicePool<stdClass> $pool
     */
    private function releaseInCoroutines(DiServicePool $pool, int ...$coroutineIds): void
    {
        foreach ($coroutineIds as $coroutineId) {
            $this->coroutineId = $coroutineId;
            $pool->releaseFromCoroutine();
        }
    }

    /**
     * @param (Closure(): void)|null $onYield what the runtime does when the pool lets the others run; without one the
     *                                        runtime cannot yield
     * @return DiServicePool<stdClass>
     */
    private function pool(?int $idleLimit, ?Closure $onYield = null): DiServicePool
    {
        $container = new ContainerBuilder();
        $container->register('pooled', stdClass::class)->setShared(false)->setPublic(true);
        $container->compile();

        $swoole = $onYield === null
            ? $this->createStub(Swoole::class)
            : $this->createStubForIntersectionOfInterfaces([Swoole::class, CoroutineYielding::class]);

        if ($onYield !== null) {
            $swoole->method('yieldToOtherCoroutines')->willReturnCallback($onYield);
        }

        $swoole->method('getCoroutineId')->willReturnCallback(fn(): int => $this->coroutineId);
        $swoole->method('getCoroutineContext')->willReturnCallback(
            fn(int $coroutineId): ?ArrayObject => isset($this->running[$coroutineId]) ? new ArrayObject() : null,
        );

        /** @var DiServicePool<stdClass> $pool */
        $pool = new DiServicePool(
            'pooled',
            $container,
            $swoole,
            new class implements Mutex {
                #[Override]
                public function acquire(): void {}

                #[Override]
                public function release(): void {}

                #[Override]
                public function isAcquired(): bool
                {
                    return false;
                }
            },
            50,
            new class implements StabilityChecker {
                #[Override]
                public function isStable(object $service): bool
                {
                    return !isset($service->unstable);
                }

                #[Override]
                public static function getSupportedClass(): string
                {
                    return stdClass::class;
                }
            },
            null,
            $idleLimit,
            new class ($this->recordDisposal(...)) implements Disposer {
                /**
                 * @param Closure(object): void $record
                 */
                public function __construct(private readonly Closure $record) {}

                #[Override]
                public function dispose(object $service): void
                {
                    ($this->record)($service);
                }

                #[Override]
                public static function getSupportedClass(): string
                {
                    return stdClass::class;
                }
            },
        );

        return $pool;
    }
}

<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Feature;

use ArrayObject;
use Override;
use PHPUnit\Framework\TestCase;
use stdClass;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\CoWrapper;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\ServicePool\BaseServicePool;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\ServicePool\ServicePool;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\ServicePool\ServicePoolContainer;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\ServicePool\ServicePoolEntry;
use SwooleBundle\SwooleBundle\Component\Locking\Channel\ChannelMutex;
use SwooleBundle\SwooleBundle\Coroutine\CoroutinePool;
use SwooleBundle\SwooleBundle\Tests\Helper\SwooleFactoryFactory;
use SwooleBundle\SwooleBundle\Tests\Helper\WaitingOnTheWayBackServicePool;

/**
 * When a coroutine waiting at a pool's limit gets the instance another coroutine gives back.
 *
 * A coroutine gives its instances back as it ends, from a deferred callback, pool after pool. Giving one back
 * wakes a coroutine waiting for it, and both runtimes switch to that one there and then, in the middle of the
 * callback. Everything the coroutine giving back does from then on - giving back its other instances, one of
 * which may wait on I/O as a connection's ping does - it does with the instance already in another's hands,
 * while it still runs. Code that keeps track of which coroutine an object belongs to (fiber viber) sees the
 * next coroutine write what one still running wrote last.
 *
 * So the waiting coroutine is to get it only once the one that gave it back has ended.
 */
final class ServicePoolHandOverTest extends TestCase
{
    /**
     * How long the first coroutine holds its instance: long enough for the last to be waiting for one.
     */
    private const float HOLD_SECONDS = 0.05;

    #[Override]
    protected function setUp(): void
    {
        if (extension_loaded('openswoole') || extension_loaded('swoole')) {
            return;
        }

        self::markTestSkipped('This test requires OpenSwoole or Swoole.');
    }

    public function testAnInstanceGivenBackReachesACoroutineWaitingForItOnlyOnceTheOneGivingItBackHasEnded(): void
    {
        $swoole = SwooleFactoryFactory::newInstance();
        $pool = new class ($swoole, new ChannelMutex(), 1) extends BaseServicePool {
            protected function newServiceInstance(): object
            {
                return new stdClass();
            }
        };
        $coWrapper = new CoWrapper(new ServicePoolContainer(
            [new ServicePoolEntry($pool), new ServicePoolEntry(new WaitingOnTheWayBackServicePool())],
        ));
        /** @var ArrayObject<string, bool|int> $observed */
        $observed = new ArrayObject();
        $waiter = static function () use ($pool, $coWrapper, $observed): void {
            $coWrapper->defer();
            // past the limit, and the lock already taken by the second: this waits for an instance given back
            $observed['waiterInstance'] = spl_object_id($pool->get());
            $observed['firstStillRunning'] = Coroutine::exists((int) $observed['first']);
        };

        CoroutinePool::fromCoroutines(
            self::holding($pool, $coWrapper, $observed, 'first', self::HOLD_SECONDS),
            self::holding($pool, $coWrapper, $observed, 'second', 4 * self::HOLD_SECONDS),
            $waiter,
        )->run();

        self::assertArrayHasKey('waiterInstance', $observed, 'The waiting coroutine never got an instance.');
        self::assertSame(
            $observed['firstInstance'],
            $observed['waiterInstance'],
            'The waiting coroutine did not get the instance the first gave back.',
        );
        self::assertFalse(
            $observed['firstStillRunning'],
            'The instance reached the coroutine waiting for it while the one that gave it back was still running.',
        );
    }

    /**
     * A coroutine that takes an instance and holds it for a while, noting which coroutine it is and which instance.
     *
     * @param ServicePool<stdClass> $pool
     * @param ArrayObject<string, bool|int> $observed
     */
    private static function holding(
        ServicePool $pool,
        CoWrapper $coWrapper,
        ArrayObject $observed,
        string $name,
        float $seconds,
    ): callable {
        return static function () use ($pool, $coWrapper, $observed, $name, $seconds): void {
            $coWrapper->defer();
            $observed[$name] = Coroutine::getCid();
            $observed[$name . 'Instance'] = spl_object_id($pool->get());
            (new Channel(1))->pop($seconds);
        };
    }
}

<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Component\Locking\RecursiveOwner;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use SwooleBundle\SwooleBundle\Common\Adapter\Swoole;
use SwooleBundle\SwooleBundle\Component\Locking\Mutex;
use SwooleBundle\SwooleBundle\Component\Locking\RecursiveOwner\RecursiveOwnerMutex;

final class RecursiveOwnerMutexTest extends TestCase
{
    use ProphecyTrait;

    /**
     * Swoole reports -1 outside a coroutine, and the server's lifecycle callbacks - onWorkerExit above
     * all - are called that way. The wrapped mutex waits on a Channel, and a Channel used outside a
     * coroutine throws "API must be called in the coroutine" rather than blocking: a worker exiting
     * while another coroutine held this mutex took that exception through its exit handler and died
     * mid-shutdown, leaving a server with no worker to answer anything.
     */
    public function testItDoesNotReachForTheWrappedMutexOutsideACoroutine(): void
    {
        $swoole = $this->prophesize(Swoole::class);
        $swoole->getCoroutineId()->willReturn(-1);

        $wrapped = $this->prophesize(Mutex::class);
        $wrapped->acquire()->shouldNotBeCalled();
        $wrapped->release()->shouldNotBeCalled();

        $mutex = new RecursiveOwnerMutex($swoole->reveal(), $wrapped->reveal());

        $mutex->acquire();
        $mutex->release();

        self::assertFalse($mutex->isAcquired());
    }

    /**
     * And the case above must not be reachable by a coroutine that simply has a low id - only by the
     * absence of one.
     */
    public function testItStillLocksInsideACoroutine(): void
    {
        $swoole = $this->prophesize(Swoole::class);
        $swoole->getCoroutineId()->willReturn(0);

        $wrapped = $this->prophesize(Mutex::class);
        $wrapped->acquire()->shouldBeCalledOnce();
        $wrapped->release()->shouldBeCalledOnce();

        $mutex = new RecursiveOwnerMutex($swoole->reveal(), $wrapped->reveal());

        $mutex->acquire();
        self::assertTrue($mutex->isAcquired());

        $mutex->release();
        self::assertFalse($mutex->isAcquired());
    }

    /**
     * The same coroutine re-entering only takes the wrapped mutex once, and gives it back once.
     */
    public function testTheOwningCoroutineCanReenter(): void
    {
        $swoole = $this->prophesize(Swoole::class);
        $swoole->getCoroutineId()->willReturn(7);

        $wrapped = $this->prophesize(Mutex::class);
        $wrapped->acquire()->shouldBeCalledOnce();
        $wrapped->release()->shouldBeCalledOnce();

        $mutex = new RecursiveOwnerMutex($swoole->reveal(), $wrapped->reveal());

        $mutex->acquire();
        $mutex->acquire();
        $mutex->release();
        self::assertTrue($mutex->isAcquired(), 'still held by its owner after one of two releases');

        $mutex->release();
        self::assertFalse($mutex->isAcquired());
    }
}

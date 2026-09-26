<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Symfony\Container\ServicePool;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Swoole\Server;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\ServicePool\DrainServicePoolsOnWorkerExit;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\ServicePool\ServicePoolContainer;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\ServicePool\ServicePoolEntry;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\SimpleResetter;
use SwooleBundle\SwooleBundle\Server\WorkerHandler\WorkerExitHandler;
use SwooleBundle\SwooleBundle\Tests\Unit\Server\SwooleSpy;

#[CoversClass(DrainServicePoolsOnWorkerExit::class)]
final class DrainServicePoolsOnWorkerExitTest extends TestCase
{
    /**
     * Inside a coroutine - where onWorkerExit runs when the server has them on - the pools are drained there and
     * then, after whatever the handler decorates has had its turn.
     */
    public function testThePoolsAreDrainedAfterTheDecoratedHandlerRuns(): void
    {
        $pool = new ServicePoolSpy();
        $decorated = new class implements WorkerExitHandler {
            private int $calls = 0;

            #[Override]
            public function handle(Server $worker, int $workerId): void
            {
                $this->calls++;
            }

            public function calls(): int
            {
                return $this->calls;
            }
        };

        $handler = new DrainServicePoolsOnWorkerExit(
            new ServicePoolContainer([new ServicePoolEntry($pool, new SimpleResetter('reset'))]),
            new SwooleSpy(coroutineId: 7),
            $decorated,
        );

        $handler->handle($this->createStub(Server::class), 0);

        self::assertSame(1, $decorated->calls());
        self::assertSame(1, $pool->drainCallCount());
    }
}

<?php

declare(strict_types=1);

namespace K911\Swoole\Tests\Unit\Coroutine;

use K911\Swoole\Component\Locking\Channel\ChannelMutex;
use PHPUnit\Framework\TestCase;
use Swoole\Coroutine\Scheduler;
use Swoole\Runtime;

final class ChannelMutexTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Runtime::enableCoroutine();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Runtime::enableCoroutine(false);
    }

    public function testMutexWorks(): void
    {
        $i = 0;
        $mutex = new ChannelMutex();
        $scheduler = new Scheduler();

        $scheduler->add(function () use (&$i, $mutex) {
            $mutex->acquire();

            $i = -1;
            usleep(1000);
            self::assertSame(-1, $i);
            $i = 1;

            $mutex->release();
        });

        $scheduler->add(function () use (&$i, $mutex) {
            $mutex->acquire();

            $i = -2;
            usleep(1000);
            self::assertSame(-2, $i);
            $i = 2;

            $mutex->release();
        });

        $scheduler->add(function () use (&$i, $mutex) {
            $mutex->acquire();

            $i = -3;
            usleep(1000);
            self::assertSame(-3, $i);
            $i = 3;

            $mutex->release();
        });

        $scheduler->start();
    }
}

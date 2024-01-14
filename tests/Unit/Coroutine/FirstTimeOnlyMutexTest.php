<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Coroutine;

use PHPUnit\Framework\TestCase;
use Swoole\Coroutine\Scheduler;
use Swoole\Runtime;
use SwooleBundle\SwooleBundle\Component\Locking\Channel\ChannelMutex;
use SwooleBundle\SwooleBundle\Component\Locking\FirstTimeOnly\FirstTimeOnlyMutex;

final class FirstTimeOnlyMutexTest extends TestCase
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
        $failureOccurred = false;
        $mutex = new FirstTimeOnlyMutex(new ChannelMutex());
        $scheduler = new Scheduler();

        $scheduler->add(function () use (&$i, $mutex) {
            try {
                $mutex->acquire();

                $i = -1;
                usleep(1000);
                self::assertSame(-1, $i);
                $i = 1;
            } finally {
                $mutex->release();
            }
        });

        $scheduler->add(function () use (&$i, &$failureOccurred, $mutex) {
            try {
                $mutex->acquire();

                $i = -2;
                usleep(1000);

                try {
                    self::assertSame(-2, $i);
                } catch (\Throwable) {
                    $failureOccurred = true;
                }

                $i = 2;
            } finally {
                $mutex->release();
            }
        });

        $scheduler->add(function () use (&$i, &$failureOccurred, $mutex) {
            try {
                $mutex->acquire();

                $i = -3;
                usleep(1000);

                try {
                    self::assertSame(-3, $i);
                } catch (\Throwable) {
                    $failureOccurred = true;
                }

                $i = 3;
            } finally {
                $mutex->release();
            }
        });

        $scheduler->start();

        self::assertTrue($failureOccurred);
    }
}

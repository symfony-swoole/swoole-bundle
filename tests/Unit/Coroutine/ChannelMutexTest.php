<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Coroutine;

use PHPUnit\Framework\TestCase;
use Swoole\Coroutine\Scheduler;
use Swoole\Runtime;
use SwooleBundle\SwooleBundle\Component\Locking\Channel\ChannelMutex;

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

    // phpcs:disable SlevomatCodingStandard.PHP.DisallowReference.DisallowedInheritingVariableByReference
    public function testMutexWorks(): void
    {
        $i = 0;
        $mutex = new ChannelMutex();
        $scheduler = new Scheduler();

        $scheduler->add(static function () use (&$i, $mutex): void {
            $mutex->acquire();

            $i = -1;
            usleep(1000);
            self::assertSame(-1, $i);
            $i = 1;

            $mutex->release();
        });

        $scheduler->add(static function () use (&$i, $mutex): void {
            $mutex->acquire();

            $i = -2;
            usleep(1000);
            self::assertSame(-2, $i);
            $i = 2;

            $mutex->release();
        });

        $scheduler->add(static function () use (&$i, $mutex): void {
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

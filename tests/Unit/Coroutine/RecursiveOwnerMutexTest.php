<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Coroutine;

use PHPUnit\Framework\TestCase;
use Swoole\Coroutine\Scheduler;
use Swoole\Runtime;
use SwooleBundle\SwooleBundle\Component\Locking\Channel\ChannelMutex;
use SwooleBundle\SwooleBundle\Component\Locking\RecursiveOwner\RecursiveOwnerMutex;

final class RecursiveOwnerMutexTest extends TestCase
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
        $mutex = new RecursiveOwnerMutex(new ChannelMutex());
        $scheduler = new Scheduler();
        $recursiveFn = static function (int $testNr) use ($mutex, &$recursiveFn): void {
            $mutex->acquire();

            $i = -$testNr;
            usleep(1000);
            self::assertSame(-$testNr, $i);
            $i = $testNr; // phpcs:ignore

            if ($testNr < 1000) {
                $recursiveFn($testNr * 10);
            }

            $mutex->release();
        };

        $scheduler->add(static function () use ($recursiveFn): void {
            $recursiveFn(1);
        });

        $scheduler->add(static function () use ($recursiveFn): void {
            $recursiveFn(2);
        });

        $scheduler->add(static function () use ($recursiveFn): void {
            $recursiveFn(3);
        });

        $scheduler->start();
    }
}

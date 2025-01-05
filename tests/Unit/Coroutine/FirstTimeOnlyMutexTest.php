<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Coroutine;

use PHPUnit\Framework\TestCase;
use Swoole\Coroutine\Scheduler;
use SwooleBundle\SwooleBundle\Bridge\OpenSwoole\OpenSwoole;
use SwooleBundle\SwooleBundle\Bridge\Swoole\Swoole;
use SwooleBundle\SwooleBundle\Common\System\Extension;
use SwooleBundle\SwooleBundle\Component\Locking\Channel\ChannelMutex;
use SwooleBundle\SwooleBundle\Component\Locking\FirstTimeOnly\FirstTimeOnlyMutex;
use Throwable;

// phpcs:disable SlevomatCodingStandard.PHP.DisallowReference.DisallowedInheritingVariableByReference
final class FirstTimeOnlyMutexTest extends TestCase
{
    public function testMutexWorks(): void
    {
        if (extension_loaded(Extension::SWOOLE)) {
            $swoole = new Swoole();
        } elseif (extension_loaded(Extension::OPENSWOOLE)) {
            $swoole = new OpenSwoole();
        } else {
            self::markTestSkipped('No supported extension loaded.');
        }

        $swoole->enableCoroutines();

        try {
            $i = 0;
            $failureOccurred = false;
            $mutex = new FirstTimeOnlyMutex(new ChannelMutex());
            $scheduler = new Scheduler();

            $scheduler->add(static function () use (&$i, $mutex): void {
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

            $scheduler->add(static function () use (&$i, &$failureOccurred, $mutex): void {
                try {
                    $mutex->acquire();

                    $i = -2;
                    usleep(1000);

                    try {
                        self::assertSame(-2, $i);
                    } catch (Throwable) {
                        $failureOccurred = true;
                    }

                    $i = 2;
                } finally {
                    $mutex->release();
                }
            });

            $scheduler->add(static function () use (&$i, &$failureOccurred, $mutex): void {
                try {
                    $mutex->acquire();

                    $i = -3;
                    usleep(1000);

                    try {
                        self::assertSame(-3, $i);
                    } catch (Throwable) {
                        $failureOccurred = true;
                    }

                    $i = 3;
                } finally {
                    $mutex->release();
                }
            });

            $scheduler->start();

            self::assertTrue($failureOccurred);
        } finally {
            $swoole->disableCoroutines();
        }
    }
}

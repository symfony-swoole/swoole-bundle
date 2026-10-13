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
                    self::assertSame(-1, $i); /** @phpstan-ignore staticMethod.alreadyNarrowedType */
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
                        self::assertSame(-2, $i); /** @phpstan-ignore staticMethod.alreadyNarrowedType */
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
                        self::assertSame(-3, $i); /** @phpstan-ignore staticMethod.alreadyNarrowedType */
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

    /**
     * Three callers at once, as a task worker's consumers are of one transport's factory: the first yields while it
     * holds the mutex, and the two that waited go on without yielding once. Each of them used to give the wrapped
     * mutex back for every one that had waited - "Cannot release an unlocked mutex." in all three.
     */
    public function testThoseThatWaitedAndDoNotYieldReleaseNothingTwice(): void
    {
        $swoole = $this->swoole();
        $swoole->enableCoroutines();

        try {
            $wrapped = new ChannelMutex();
            $mutex = new FirstTimeOnlyMutex($wrapped);
            $thrown = [];
            $done = 0;
            $scheduler = new Scheduler();

            for ($caller = 0; $caller < 3; ++$caller) {
                $scheduler->add(static function () use ($mutex, $caller, &$thrown, &$done): void {
                    try {
                        $mutex->acquire();

                        try {
                            if ($caller === 0) {
                                usleep(1000);
                            }
                        } finally {
                            $mutex->release();
                        }

                        ++$done;
                    } catch (Throwable $throwable) {
                        $thrown[] = $throwable->getMessage();
                    }
                });
            }

            $scheduler->start();

            self::assertSame([], $thrown);
            self::assertSame(3, $done);
            self::assertFalse($wrapped->isAcquired());
        } finally {
            $swoole->disableCoroutines();
        }
    }

    /**
     * What happens to the wrapped mutex adds up: each coroutine that took it is the one that gives it back, and one
     * that comes once the first has let go takes nothing.
     */
    public function testEachCoroutineGivesBackWhatItTook(): void
    {
        $swoole = $this->swoole();
        $swoole->enableCoroutines();

        try {
            $wrapped = new RecordingMutex(new ChannelMutex(), $swoole);
            $mutex = new FirstTimeOnlyMutex($wrapped);
            $scheduler = new Scheduler();

            for ($caller = 0; $caller < 3; ++$caller) {
                $scheduler->add(static function () use ($mutex, $caller): void {
                    $mutex->acquire();

                    try {
                        if ($caller === 0) {
                            usleep(1000);
                        }
                    } finally {
                        $mutex->release();
                    }
                });
            }

            $scheduler->start();

            $said = $wrapped->said();
            self::assertCount(6, $said);

            $holding = [];
            foreach ($said as [$what, $coroutine]) {
                if ($what === 'held') {
                    self::assertSame([], $holding, 'Two coroutines hold the mutex at once.');
                    $holding = [$coroutine];

                    continue;
                }

                self::assertSame([$coroutine], $holding, 'A coroutine gave back what another held.');
                $holding = [];
            }

            self::assertSame([], $holding);

            $late = new Scheduler();
            $late->add(static function () use ($mutex): void {
                $mutex->acquire();
                $mutex->release();
            });
            $late->start();

            self::assertCount(6, $wrapped->said());
        } finally {
            $swoole->disableCoroutines();
        }
    }

    private function swoole(): Swoole|OpenSwoole
    {
        if (extension_loaded(Extension::SWOOLE)) {
            return new Swoole();
        }

        if (extension_loaded(Extension::OPENSWOOLE)) {
            return new OpenSwoole();
        }

        self::markTestSkipped('No supported extension loaded.');
    }
}

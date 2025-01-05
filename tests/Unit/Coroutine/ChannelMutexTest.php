<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Coroutine;

use PHPUnit\Framework\TestCase;
use Swoole\Coroutine\Scheduler;
use SwooleBundle\SwooleBundle\Bridge\OpenSwoole\OpenSwoole;
use SwooleBundle\SwooleBundle\Bridge\Swoole\Swoole;
use SwooleBundle\SwooleBundle\Common\System\Extension;
use SwooleBundle\SwooleBundle\Component\Locking\Channel\ChannelMutex;

final class ChannelMutexTest extends TestCase
{
    // phpcs:disable SlevomatCodingStandard.PHP.DisallowReference.DisallowedInheritingVariableByReference
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
        } finally {
            $swoole->disableCoroutines();
        }
    }
}

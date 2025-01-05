<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Coroutine;

use PHPUnit\Framework\TestCase;
use Swoole\Coroutine\Scheduler;
use SwooleBundle\SwooleBundle\Bridge\OpenSwoole\OpenSwoole;
use SwooleBundle\SwooleBundle\Bridge\Swoole\Swoole;
use SwooleBundle\SwooleBundle\Common\System\Extension;
use SwooleBundle\SwooleBundle\Component\Locking\Channel\ChannelMutex;
use SwooleBundle\SwooleBundle\Component\Locking\RecursiveOwner\RecursiveOwnerMutex;

final class RecursiveOwnerMutexTest extends TestCase
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
            $mutex = new RecursiveOwnerMutex($swoole, new ChannelMutex());
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
        } finally {
            $swoole->disableCoroutines();
        }
    }
}

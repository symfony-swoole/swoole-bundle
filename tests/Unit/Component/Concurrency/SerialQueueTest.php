<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Component\Concurrency;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Swoole\Coroutine\Scheduler;
use SwooleBundle\SwooleBundle\Bridge\OpenSwoole\OpenSwoole;
use SwooleBundle\SwooleBundle\Bridge\Swoole\Swoole as SwooleAdapter;
use SwooleBundle\SwooleBundle\Common\Adapter\Swoole;
use SwooleBundle\SwooleBundle\Common\System\Extension;
use SwooleBundle\SwooleBundle\Component\Concurrency\SerialQueue;

final class SerialQueueTest extends TestCase
{
    private const int PRODUCERS = 100;

    // phpcs:disable SlevomatCodingStandard.PHP.DisallowReference.DisallowedInheritingVariableByReference
    /**
     * Recording entering and leaving the consumer covers both guarantees at once: a payload may only be
     * entered once the previous one was left, and it has to happen in submission order.
     */
    public function testPayloadsAreConsumedInSubmissionOrderAndNeverConcurrently(): void
    {
        $swoole = $this->swoole();
        $swoole->enableCoroutines();

        try {
            $events = [];

            $scheduler = new Scheduler();
            $scheduler->add(static function () use ($swoole, &$events): void {
                $queue = new SerialQueue($swoole, static function (mixed $payload) use (&$events): void {
                    $events[] = sprintf('enter:%d', $payload);

                    // yields, giving every other coroutine a chance to break the serialization
                    usleep(100);

                    $events[] = sprintf('leave:%d', $payload);
                });

                for ($i = 0; $i < self::PRODUCERS; $i++) {
                    go(static function () use ($queue, $i): void {
                        $queue->submit($i);
                    });
                }
            });
            $scheduler->start();

            $expected = [];

            for ($i = 0; $i < self::PRODUCERS; $i++) {
                $expected[] = sprintf('enter:%d', $i);
                $expected[] = sprintf('leave:%d', $i);
            }

            self::assertSame($expected, $events);
        } finally {
            $swoole->disableCoroutines();
        }
    }

    public function testDrainWaitsForEverythingSubmittedByOtherCoroutines(): void
    {
        $swoole = $this->swoole();
        $swoole->enableCoroutines();

        try {
            $consumed = [];
            $consumedOnDrain = null;

            $scheduler = new Scheduler();
            $queue = new SerialQueue($swoole, static function (mixed $payload) use (&$consumed): void {
                usleep(1000);
                $consumed[] = $payload;
            });

            $scheduler->add(static function () use ($queue): void {
                for ($i = 0; $i < 10; $i++) {
                    $queue->submit($i);
                }
            });

            $scheduler->add(static function () use ($queue, &$consumed, &$consumedOnDrain): void {
                $queue->drain();

                $consumedOnDrain = $consumed;
            });

            $scheduler->start();

            self::assertSame(range(0, 9), $consumedOnDrain);
        } finally {
            $swoole->disableCoroutines();
        }
    }

    public function testSubmittingOutsideOfACoroutineConsumesRightAway(): void
    {
        $consumed = [];
        $queue = new SerialQueue($this->swoole(), static function (mixed $payload) use (&$consumed): void {
            $consumed[] = $payload;
        });

        $queue->submit('first');
        $queue->submit('second');

        self::assertSame(['first', 'second'], $consumed);
        self::assertTrue($queue->isEmpty());
    }

    public function testDrainingOutsideOfACoroutineDoesNotFail(): void
    {
        $queue = new SerialQueue($this->swoole(), static function (mixed $payload): void {});

        $queue->drain();

        self::assertTrue($queue->isEmpty());
    }

    public function testAFailingPayloadDoesNotStopTheConsumer(): void
    {
        $swoole = $this->swoole();
        $swoole->enableCoroutines();

        try {
            $consumed = [];

            $scheduler = new Scheduler();
            $scheduler->add(static function () use ($swoole, &$consumed): void {
                $queue = new SerialQueue($swoole, static function (mixed $payload) use (&$consumed): void {
                    if ($payload === 'boom') {
                        throw new RuntimeException('consumer blew up');
                    }

                    $consumed[] = $payload;
                });

                $queue->submit('before');
                $queue->submit('boom');
                $queue->submit('after');
            });
            $scheduler->start();

            self::assertSame(['before', 'after'], $consumed);
        } finally {
            $swoole->disableCoroutines();
        }
    }

    /**
     * Reentrant drains happen for real - the rotating file handler closes itself from within its own
     * write(), which is exactly where the consumer callback runs.
     */
    public function testDrainingFromWithinTheConsumerIsANoop(): void
    {
        $swoole = $this->swoole();
        $swoole->enableCoroutines();

        try {
            $consumed = [];
            $queue = null;

            $scheduler = new Scheduler();
            $scheduler->add(static function () use ($swoole, &$consumed, &$queue): void {
                $queue = new SerialQueue($swoole, static function (mixed $payload) use (&$consumed, &$queue): void {
                    $consumed[] = $payload;

                    self::assertInstanceOf(SerialQueue::class, $queue);
                    $queue->drain();
                });

                $queue->submit('first');
                $queue->submit('second');
            });
            $scheduler->start();

            self::assertSame(['first', 'second'], $consumed);
        } finally {
            $swoole->disableCoroutines();
        }
    }

    private function swoole(): Swoole
    {
        if (extension_loaded(Extension::SWOOLE)) {
            return new SwooleAdapter();
        }

        if (extension_loaded(Extension::OPENSWOOLE)) {
            return new OpenSwoole();
        }

        self::markTestSkipped('No supported extension loaded.');
    }
}

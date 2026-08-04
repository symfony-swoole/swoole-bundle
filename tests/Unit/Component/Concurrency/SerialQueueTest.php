<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Component\Concurrency;

use Assert\Assertion;
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

    private string $previousErrorLog = '';

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

    public function testAFailingPayloadDoesNotStopTheConsumerAndIsReported(): void
    {
        $swoole = $this->swoole();
        $swoole->enableCoroutines();
        // the consumer reports what it swallowed through error_log(), which without somewhere to put it
        // ends up on the suite's own output
        $reportDestination = $this->captureErrorLog();

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
            self::assertStringContainsString(
                'Serially queued work failed: consumer blew up',
                (string) file_get_contents($reportDestination),
            );
        } finally {
            $this->releaseErrorLog($reportDestination);
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

    /**
     * The whole point of pinning: whatever the consumer acquired stays with the coroutine that acquired it,
     * so a resource opened while consuming one payload is still that coroutine's when the next arrives.
     */
    public function testAPinnedConsumerStaysTheSameCoroutineAcrossSeparateSubmissions(): void
    {
        $swoole = $this->swoole();
        $swoole->enableCoroutines();

        try {
            $consumerContextIds = [];

            $scheduler = new Scheduler();
            $scheduler->add(static function () use ($swoole, &$consumerContextIds): void {
                $queue = new SerialQueue(
                    $swoole,
                    static function (mixed $payload) use ($swoole, &$consumerContextIds): void {
                        $consumerContextIds[$payload] = $swoole->getCoroutineId();
                    },
                    SerialQueue::DEFAULT_CAPACITY,
                    static function (): void {},
                );

                $queue->submit('first');
                // long enough for an unpinned consumer to have run dry and left, short enough to stay well
                // inside the idle timeout
                usleep(50_000);
                $queue->submit('second');
            });
            $scheduler->start();

            self::assertCount(2, $consumerContextIds);
            self::assertSame($consumerContextIds['first'], $consumerContextIds['second']);
        } finally {
            $swoole->disableCoroutines();
        }
    }

    public function testAPinnedConsumerReleasesWhatItHoldsBeforeGivingUpOnAnIdleQueue(): void
    {
        $swoole = $this->swoole();
        $swoole->enableCoroutines();

        try {
            $released = 0;

            $scheduler = new Scheduler();
            $scheduler->add(static function () use ($swoole, &$released): void {
                $queue = new SerialQueue(
                    $swoole,
                    static function (mixed $payload): void {},
                    SerialQueue::DEFAULT_CAPACITY,
                    static function () use (&$released): void {
                        ++$released;
                    },
                    0.05,
                );

                $queue->submit('payload');
                usleep(200_000);
            });
            $scheduler->start();

            self::assertSame(1, $released);
        } finally {
            $swoole->disableCoroutines();
        }
    }

    /**
     * Work handed over this way is what a pooled monolog handler does with its reset: the file was opened
     * by the consumer, so closing it anywhere else is the cross-coroutine access the queue exists to avoid.
     */
    public function testWorkHandedOverRunsInTheConsumerBehindEverythingSubmittedBeforeIt(): void
    {
        $swoole = $this->swoole();
        $swoole->enableCoroutines();

        try {
            $events = [];
            $taskContextId = null;
            $consumerContextId = null;

            $scheduler = new Scheduler();
            $scheduler->add(
                static function () use ($swoole, &$events, &$taskContextId, &$consumerContextId): void {
                    $queue = new SerialQueue(
                        $swoole,
                        static function (mixed $payload) use ($swoole, &$events, &$consumerContextId): void {
                            usleep(1000);
                            $events[] = $payload;
                            $consumerContextId = $swoole->getCoroutineId();
                        },
                        SerialQueue::DEFAULT_CAPACITY,
                        static function (): void {},
                    );

                    for ($i = 0; $i < 5; $i++) {
                        $queue->submit($i);
                    }

                    $queue->runInConsumer(static function () use ($swoole, &$events, &$taskContextId): void {
                        $events[] = 'task';
                        $taskContextId = $swoole->getCoroutineId();
                    });
                },
            );
            $scheduler->start();

            self::assertSame([0, 1, 2, 3, 4, 'task'], $events);
            self::assertNotNull($taskContextId);
            self::assertSame($consumerContextId, $taskContextId);
        } finally {
            $swoole->disableCoroutines();
        }
    }

    /**
     * Monolog resets a stream handler by closing it, so the task handed over ends up asking for another one
     * from inside the consumer. Queueing that up would wait for a consumer which cannot get to it before
     * the call it is waiting on returns.
     */
    public function testWorkHandedOverFromWithinTheConsumerRunsRightAway(): void
    {
        $swoole = $this->swoole();
        $swoole->enableCoroutines();

        try {
            $events = [];
            $queue = null;

            $scheduler = new Scheduler();
            $scheduler->add(static function () use ($swoole, &$events, &$queue): void {
                $queue = new SerialQueue(
                    $swoole,
                    static function (mixed $payload) use (&$events, &$queue): void {
                        $events[] = $payload;

                        self::assertInstanceOf(SerialQueue::class, $queue);
                        $queue->runInConsumer(static function () use (&$events): void {
                            $events[] = 'nested';
                        });
                    },
                    SerialQueue::DEFAULT_CAPACITY,
                    static function (): void {},
                );

                $queue->submit('payload');
            });
            $scheduler->start();

            self::assertSame(['payload', 'nested'], $events);
        } finally {
            $swoole->disableCoroutines();
        }
    }

    public function testWorkHandedOverWithoutAConsumerToHandItToRunsRightAway(): void
    {
        $ran = false;
        $queue = new SerialQueue(
            $this->swoole(),
            static function (mixed $payload): void {},
            SerialQueue::DEFAULT_CAPACITY,
            static function (): void {},
        );

        $queue->runInConsumer(static function () use (&$ran): void {
            $ran = true;
        });

        self::assertTrue($ran);
    }

    /**
     * Points error_log() at a file of its own and says where, so what a test provokes on purpose can be
     * read back instead of being printed over the suite's output.
     */
    private function captureErrorLog(): string
    {
        $destination = tempnam(sys_get_temp_dir(), 'serial-queue-report-');
        Assertion::string($destination);

        $this->previousErrorLog = (string) ini_get('error_log');
        ini_set('error_log', $destination);

        return $destination;
    }

    private function releaseErrorLog(string $destination): void
    {
        ini_set('error_log', $this->previousErrorLog);
        @unlink($destination);
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

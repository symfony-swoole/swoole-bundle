<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Coroutine;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Swoole\Coroutine\Channel;
use SwooleBundle\SwooleBundle\Coroutine\CoroutinePool;

// phpcs:disable SlevomatCodingStandard.PHP.DisallowReference.DisallowedInheritingVariableByReference
final class CoroutinePoolTest extends TestCase
{
    public function testCoroutinePoolWorks(): void
    {
        $value = null;
        $expected = 1;

        $pool = CoroutinePool::fromCoroutines(
            static function () use (&$value, $expected): void {
                $value = $expected;
            },
        );
        $pool->run();

        self::assertSame($expected, $value);
    }

    public function testCoroutinePoolWithManyCoroutinesWorks(): void
    {
        $value1 = null;
        $expected1 = 1;

        $value2 = null;
        $expected2 = 2;

        $value3 = null;
        $expected3 = 3;

        $pool = CoroutinePool::fromCoroutines(
            static function () use (&$value1, $expected1): void {
                $value1 = $expected1;
            },
            static function () use (&$value2, $expected2): void {
                $value2 = $expected2;
            },
            static function () use (&$value3, $expected3): void {
                $value3 = $expected3;
            }
        );
        $pool->run();

        self::assertSame($expected1, $value1);
        self::assertSame($expected2, $value2);
        self::assertSame($expected3, $value3);
    }

    public function testCoroutinePoolInCoroutinePoolWorks(): void
    {
        $value1 = null;
        $expected1 = 1;

        $value2 = null;
        $expected2 = 2;

        $pool1 = CoroutinePool::fromCoroutines(
            static function () use (&$value1, &$value2, $expected1, $expected2): void {
                $pool2 = CoroutinePool::fromCoroutines(
                    static function () use (&$value2, $expected2): void {
                        $value2 = $expected2;
                    },
                );
                $pool2->run();
                $value1 = $expected1;
            },
        );
        $pool1->run();

        self::assertSame($expected1, $value1);
        self::assertSame($expected2, $value2);
    }

    public function testAPoolRunInsideACoroutineWaitsForItsCoroutines(): void
    {
        // What the inner pool gave the coroutine that ran it: its results, once they are all there.
        $results = CoroutinePool::fromCoroutines(
            static fn(): array => CoroutinePool::fromCoroutines(
                static function (): string {
                    // Gives way to the other coroutines, the same way on Swoole and OpenSwoole.
                    (new Channel(1))->pop(0.01);

                    return 'slow';
                },
                static fn(): string => 'fast',
            )->run(),
        )->run();

        self::assertSame([['fast', 'slow']], $results);
    }

    public function testAPoolRunInsideACoroutineThrowsWhatItsCoroutineThrew(): void
    {
        $results = CoroutinePool::fromCoroutines(
            static function (): string {
                try {
                    CoroutinePool::fromCoroutines(
                        static function (): never {
                            // Gives way to the other coroutines, the same way on Swoole and OpenSwoole.
                            (new Channel(1))->pop(0.01);

                            throw new RuntimeException('thrown inside');
                        },
                    )->run();
                } catch (RuntimeException $exception) {
                    return $exception->getMessage();
                }

                return 'nothing thrown';
            },
        )->run();

        self::assertSame(['thrown inside'], $results);
    }
}

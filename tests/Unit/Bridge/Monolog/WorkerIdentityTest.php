<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Monolog;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SwooleBundle\SwooleBundle\Bridge\Monolog\WorkerIdentity;

final class WorkerIdentityTest extends TestCase
{
    /**
     * The boundary is the whole of this: swoole hands out one series of ids for both kinds of worker, so
     * with four http workers, worker 3 is the last web one and worker 4 is the first task one - which is
     * the first configured group of commands, and has to be labelled task-0 for that to be readable.
     */
    #[DataProvider('workerDataProvider')]
    public function testThatTaskWorkersAreNumberedAfterTheHttpOnes(int $workerId, string $expected): void
    {
        self::assertSame($expected, WorkerIdentity::labelFor(4, $workerId));
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function workerDataProvider(): iterable
    {
        yield 'the first http worker' => [0, 'web-0'];
        yield 'the last http worker' => [3, 'web-3'];
        yield 'the first task worker' => [4, 'task-0'];
        yield 'the third task worker' => [6, 'task-2'];
    }

    /**
     * Nothing has started, so there is nothing to say - and saying nothing is what keeps a console
     * process from claiming to be a worker of a server it never joined.
     */
    public function testThatAProcessThatIsNoWorkerHasNoLabel(): void
    {
        self::assertNull((new WorkerIdentity())->label());
    }
}

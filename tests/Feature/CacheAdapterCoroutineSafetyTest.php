<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Feature;

use Override;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Test\ServerTestCase;

/**
 * Regression coverage for CacheAdapterProcessor's handling of the profiler's cache recorder.
 *
 * With the profiler on, Symfony wraps every cache pool in a TraceableAdapter that appends an entry to
 * its own $calls on every cache operation, for the Cache panel to read. That decorator is not an
 * AbstractAdapter, so the processor never pooled it, and its per-request call log ended up shared by
 * every coroutine in the worker - one request writing into another's log, and CacheDataCollector::reset()
 * clearing a log being written to.
 *
 * Two things have to hold at once here, and they pull in opposite directions:
 *
 * - the recorder is per-request state, so each coroutine needs one of its own;
 * - the pool it wraps is a cache, worth having only if every coroutine sees the same one.
 *
 * The pool asked about is a Doctrine one on purpose. FrameworkBundle's own pools (cache.app,
 * cache.system, ...) are already pooled per coroutine for unrelated reasons and so cannot tell whether
 * the recorder is being handled at all; the pools DoctrineBundle registers are the ones left shared.
 *
 * @see \SwooleBundle\SwooleBundle\Bridge\Symfony\Cache\CacheAdapterProcessor
 */
final class CacheAdapterCoroutineSafetyTest extends ServerTestCase
{
    private const string ENV = 'coroutines_profiler';

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        // what is asserted is a property of the compiled container, and a container compiled before this
        // was handled answers just as happily from the cache
        $this->deleteVarDirectory();
    }

    public function testTheCacheRecorderIsPerCoroutineWhileItsCacheStaysShared(): void
    {
        $process = $this->createConsoleProcess(['test:cache-adapter:proxy-check'], [
            'APP_ENV' => self::ENV,
            'WORKER_COUNT' => '1',
        ]);
        $process->setTimeout(self::coverageEnabled() ? 60 : 30);
        $process->run();

        $this->assertProcessSucceeded($process);

        $output = $process->getOutput();

        self::assertStringContainsString('traced cache pool IS proxified.', $output);

        // the regression itself: the call log is per-request state and must not be shared
        self::assertStringContainsString('coroutines DO NOT SHARE the call log.', $output);

        // and the other half, which pooling must not break: the cache behind the recorder is shared on
        // purpose, and handing every coroutine one of its own would quietly cost every cache hit
        self::assertStringContainsString('coroutines SHARE the cache.', $output);
        self::assertStringContainsString('cache WORKS.', $output);
    }
}

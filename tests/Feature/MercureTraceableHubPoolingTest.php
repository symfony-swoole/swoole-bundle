<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Feature;

use Override;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Test\ServerTestCase;

/**
 * That two coroutines publishing through a traced Mercure hub at once each keep a trace of their own.
 *
 * With the profiler on, MercureBundle decorates every hub with a TraceableHub, and each publish appends what
 * it sent to one array on the decorator. Shared by a worker, that array is written by every coroutine that
 * publishes, and fiber viber stops the first two to overlap:
 *
 *   Cross-coroutine access detected: [property_fetch_w] Symfony\Component\Mercure\Debug\TraceableHub::$messages
 *   is owned by coroutine #2 but accessed by coroutine #3
 *
 * A messenger consumer running handlers on coroutines is exactly that worker, and outside a request nothing
 * ever resets the array either, so it also grows for the life of the process.
 *
 * Like the http client test, deliberately not a fiber viber test: a trace holding another coroutine's
 * updates is the defect whether or not anything watches ownership, and asserting the traces states it
 * directly. Nothing reaches a hub - the decorator wraps a NullHub, so the fixture app needs neither
 * MercureBundle nor a running Mercure server.
 *
 * @see \SwooleBundle\SwooleBundle\Bridge\Symfony\Mercure\MercureProcessor
 */
final class MercureTraceableHubPoolingTest extends ServerTestCase
{
    private const string ENVIRONMENT = 'mercure';

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->deleteVarDirectory();
    }

    public function testEachCoroutineKeepsATraceOfItsOwn(): void
    {
        $report = $this->report();

        self::assertStringContainsString('traceable=yes', $report, sprintf(
            'The hub the command was given is not the traced one, so the decoration did not take and nothing '
            . 'below this proves anything. %s',
            $report,
        ));
        self::assertStringContainsString('pooled=yes', $report, sprintf(
            'The traced hub was left shared, so every coroutine publishing writes one trace. %s',
            $report,
        ));
        self::assertStringContainsString('coroutines=2', $report, sprintf(
            'One of the two coroutines never reported. %s',
            $report,
        ));
        self::assertStringContainsString('own_trace_only=yes', $report, sprintf(
            'A coroutine read back updates it did not publish, which is one trace written by both. %s',
            $report,
        ));
    }

    /**
     * The other half of what pooling has to be: the same decorator for the whole of a coroutine's work.
     *
     * A pool handing out a fresh hub on every call would pass the test above - every trace would hold one
     * update, its own - while tracing nothing across the calls a coroutine actually made.
     */
    public function testACoroutineKeepsTheHubItWasGiven(): void
    {
        $report = $this->report();

        self::assertStringContainsString('traced=2,2', $report, sprintf(
            'Each coroutine published twice, and its trace does not hold exactly those two. %s',
            $report,
        ));
    }

    private function report(): string
    {
        $process = $this->createConsoleProcess(
            ['test:mercure:traceable-hub-report'],
            ['APP_ENV' => self::ENVIRONMENT],
        );
        $process->setTimeout(60);
        $process->run();

        $this->assertProcessSucceeded($process);

        return trim($process->getOutput() . $process->getErrorOutput());
    }
}

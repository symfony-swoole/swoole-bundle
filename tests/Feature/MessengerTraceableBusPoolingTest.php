<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Feature;

use Override;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Test\ServerTestCase;

/**
 * Guards that the traceable buses the messenger data collector holds are pooled per coroutine.
 *
 * MessengerPass hands `data_collector.messenger` the TraceableMessageBus decorating each bus. The
 * collector is pooled, being a data collector; the bus was not, because TraceableMessageBus
 * implements no ResetInterface and so never picked up the `kernel.reset` tag that would have made
 * StatefulServicesPass notice it. One shared bus therefore sat behind every pooled collector.
 *
 * The bus is stateful: dispatch() appends to $dispatchedMessages, reset() empties it, and the
 * collector's own reset() calls through - so every request wrote that property on the way out. Two
 * requests tearing down at once wrote it from two coroutines and fiber viber stopped the second:
 *
 *   Cross-coroutine access detected: [property_write]
 *   Symfony\Component\Messenger\TraceableMessageBus::$dispatchedMessages is owned by coroutine #460
 *   but accessed by coroutine #462
 *
 * Asserted through the collector rather than the container, because the collector holds whatever the
 * registerBus() call was compiled with, and that is the reference the failing write went through.
 *
 * @see \SwooleBundle\SwooleBundle\Bridge\Symfony\Messenger\MessengerProcessor
 */
final class MessengerTraceableBusPoolingTest extends ServerTestCase
{
    private const string ENV = 'coroutines_profiler';

    private const int PROCESS_TIMEOUT_SECONDS = 60;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->deleteVarDirectory();
    }

    public function testEveryTraceableBusHeldByTheDataCollectorIsPooled(): void
    {
        $check = $this->createConsoleProcess(
            ['test:messenger:traceable-bus-proxy-check'],
            ['APP_ENV' => self::ENV],
        );
        $check->setTimeout(self::PROCESS_TIMEOUT_SECONDS);
        $check->run();

        $this->assertProcessSucceeded($check);

        $output = $check->getOutput();

        // A pass with no buses at all would assert nothing, and is indistinguishable in the lines below
        // from a pass with every bus pooled.
        self::assertStringNotContainsString('Buses registered: 0', $output, 'no traceable bus was registered.');
        self::assertStringNotContainsString(
            'IS NOT proxified',
            $output,
            sprintf("a traceable bus is shared across coroutines.\n%s", $output),
        );
        self::assertStringContainsString('IS proxified', $output);
        self::assertStringContainsString('Collector reset.', $output);
    }
}

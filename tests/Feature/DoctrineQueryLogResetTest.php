<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Feature;

use Override;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Test\ServerTestCase;

/**
 * Doctrine's query log, which a long-running process keeps for as long as it lives.
 *
 * With profiling on, BacktraceDebugDataHolder records every query it has seen plus a full
 * debug_backtrace() with each one, and only reset() empties it. DoctrineProcessor puts it into
 * Symfony's services_resetter for exactly that reason - and StatefulServicesPass runs the compile
 * processors and then reduces the resetter down to the services whose tag asks to be reset on each
 * request, so without that tag the processor's work is undone a few lines after it is done.
 *
 * Nothing about that is visible in a container assertion: the definitions look right at the moment the
 * processor finishes. It only shows up once the whole container has been compiled and something asks
 * the resetter to do its job, which is what this does. Measured before the tag was added: ~10 MiB/min
 * in a worker, until the process died of the memory limit and its read models stopped updating.
 *
 * @see \SwooleBundle\SwooleBundle\Bridge\Doctrine\DoctrineProcessor
 */
final class DoctrineQueryLogResetTest extends ServerTestCase
{
    private const string ENV = 'coroutines_profiler';

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->deleteVarDirectory();
    }

    public function testTheServiceResetterEmptiesTheQueryLog(): void
    {
        $process = $this->createConsoleProcess(['test:doctrine:query-log-reset-check'], [
            'APP_ENV' => self::ENV,
            'WORKER_COUNT' => '1',
        ]);
        $process->setTimeout(self::coverageEnabled() ? 60 : 30);
        $process->run();

        $this->assertProcessSucceeded($process);

        $output = $process->getOutput();

        // The queries have to be there first, or an empty log afterwards would prove nothing.
        self::assertStringContainsString('queries logged: 3', $output);
        self::assertStringContainsString('queries logged after reset: 0', $output);
    }
}

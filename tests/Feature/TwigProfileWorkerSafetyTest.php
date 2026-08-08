<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Feature;

use Override;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Test\ServerTestCase;

/**
 * The root of Twig's profiling tree, which a worker keeps alive across every render it serves.
 *
 * With the Twig profiler on, rendering enters and leaves nodes of one Profile, and TwigDataCollector
 * empties it again at the end of the request. Shared, concurrent renders nest their template stacks
 * inside one another and whichever request finishes first throws away the tree the rest are still
 * filling in - the reset lands on a profile another coroutine is writing to.
 *
 * Nothing else picks the profile up: it is not resettable in Symfony's sense and carries no tag saying
 * it holds state, which is why the bundle has to say so itself.
 *
 * @see \SwooleBundle\SwooleBundle\Bridge\Symfony\Twig\TwigProcessor
 */
final class TwigProfileWorkerSafetyTest extends ServerTestCase
{
    private const string ENV = 'coroutines_profiler';

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->deleteVarDirectory();
    }

    public function testEveryCoroutineRendersIntoAProfileOfItsOwn(): void
    {
        $process = $this->createConsoleProcess(['test:twig-profile:proxy-check'], [
            'APP_ENV' => self::ENV,
            'WORKER_COUNT' => '1',
        ]);
        $process->setTimeout(self::coverageEnabled() ? 60 : 30);
        $process->run();

        $this->assertProcessSucceeded($process);

        $output = $process->getOutput();

        self::assertStringContainsString('twig profile IS proxified.', $output);
        self::assertStringContainsString('coroutines DO NOT SHARE the profile.', $output);
    }
}

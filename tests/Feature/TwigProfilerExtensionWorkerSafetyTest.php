<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Feature;

use Override;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Test\ServerTestCase;

/**
 * The Twig extensions that count where a render is, which a worker keeps alive across every render.
 *
 * `twig.extension.profiler` holds the stack of open profiles and a stopwatch event per profile;
 * `twig.extension.webprofiler` holds a depth counter and the memory stream profiler_dump() writes into.
 * Every template, block and macro entered and left writes to them, so sharing one instance between the
 * renders a worker has in flight has two requests pushing and popping the same stack:
 *
 *   FiberViber\ConcurrencyException: Cross-coroutine access detected: [property_write_preinc]
 *   Symfony\Bundle\WebProfilerBundle\Twig\WebProfilerExtension::$stackLevel is owned by coroutine #2275
 *   but accessed by coroutine #2277
 *
 * The Environment is already pooled, so the fix is to stop sharing them: an extension belonging to the
 * Environment it was added to is an extension per coroutine.
 *
 * @see \SwooleBundle\SwooleBundle\Bridge\Symfony\Twig\TwigProcessor
 */
final class TwigProfilerExtensionWorkerSafetyTest extends ServerTestCase
{
    private const string ENV = 'coroutines_profiler';

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->deleteVarDirectory();
    }

    public function testEveryCoroutineRendersThroughProfilerExtensionsOfItsOwn(): void
    {
        $process = $this->createConsoleProcess(['test:twig-profiler-extensions:sharing-check'], [
            'APP_ENV' => self::ENV,
            'WORKER_COUNT' => '1',
        ]);
        $process->setTimeout(self::coverageEnabled() ? 60 : 30);
        $process->run();

        $this->assertProcessSucceeded($process);

        $output = $process->getOutput();

        self::assertStringContainsString(
            'coroutines DO NOT SHARE Symfony\Bridge\Twig\Extension\ProfilerExtension.',
            $output,
        );
        self::assertStringContainsString(
            'coroutines DO NOT SHARE Symfony\Bundle\WebProfilerBundle\Twig\WebProfilerExtension.',
            $output,
        );
    }
}

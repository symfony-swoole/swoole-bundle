<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Feature;

use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Test\ServerTestCase;

/**
 * A lazy service has to arrive with its constructor already run, through the factories the bundle
 * generates over Symfony's own.
 *
 * Symfony builds a lazy service as a native ghost: the first call stores an empty instance and returns
 * it, and the constructor runs later, when something first touches the object and PHP calls the
 * initializer - which calls the same factory back, passing the ghost itself where the flag saying what
 * to return normally goes. Symfony's generated code allows for that by asking `true === $lazyLoad`.
 *
 * The bundle wraps those factories in a subclass of its own to make them coroutine-safe, and that
 * wrapper has the same question to answer. Asking it as `if ($lazyLoad)` reads the ghost as "hand back
 * the lazy instance", so the wrapper returns the stored instance and the constructor is never run - and
 * nothing says so until something reads a typed property off it, requests later and elsewhere.
 *
 * @see \SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Modifier\Builder\Symfony63PlusBuilder
 */
final class LazyServiceConstructionTest extends ServerTestCase
{
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->deleteVarDirectory();
    }

    /**
     * @param array<string, string> $envs
     */
    #[DataProvider('lazyServiceEnvironmentDataProvider')]
    public function testALazyServiceIsConstructedBeforeItIsUsed(array $envs): void
    {
        $process = $this->createConsoleProcess(['test:lazy-ghost:proxy-check'], $envs);
        $process->setTimeout(self::coverageEnabled() ? 60 : 30);
        $process->run();

        $this->assertProcessSucceeded($process);

        // reading a promoted property is the only thing that tells a constructed service apart from a
        // ghost that was handed over untouched
        self::assertStringContainsString('lazy ghost SAYS: lazy ghost is constructed', $process->getOutput());
    }

    /**
     * @return iterable<string, array{array<string, string>}>
     */
    public static function lazyServiceEnvironmentDataProvider(): iterable
    {
        foreach (['1', '0'] as $debug) {
            yield sprintf('debug %s', $debug === '1' ? 'on' : 'off') => [
                ['APP_ENV' => 'coroutines', 'APP_DEBUG' => $debug, 'WORKER_COUNT' => '1', 'REACTOR_COUNT' => '1'],
            ];
        }
    }
}

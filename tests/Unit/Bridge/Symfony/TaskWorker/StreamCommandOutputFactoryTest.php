<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Symfony\TaskWorker;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SwooleBundle\SwooleBundle\Bridge\Symfony\TaskWorker\StreamCommandOutputFactory;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;

final class StreamCommandOutputFactoryTest extends TestCase
{
    /**
     * These commands never go through Application::run(), which is what normally reads -v off the input,
     * so without this the verbosity written in configuration would parse and then do nothing.
     */
    #[DataProvider('verbosityProvider')]
    public function testThatVerbosityIsTakenFromTheCommandLine(string $commandLine, int $expected): void
    {
        $output = (new StreamCommandOutputFactory())->newOutput($commandLine);

        self::assertSame($expected, $output->getVerbosity());
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function verbosityProvider(): iterable
    {
        yield 'default' => ['messenger:consume default', OutputInterface::VERBOSITY_NORMAL];

        yield 'verbose' => ['messenger:consume default -v', OutputInterface::VERBOSITY_VERBOSE];

        yield 'very verbose' => ['messenger:consume default -vv', OutputInterface::VERBOSITY_VERY_VERBOSE];

        yield 'debug' => ['messenger:consume default -vvv', OutputInterface::VERBOSITY_DEBUG];

        yield 'quiet' => ['messenger:consume default -q', OutputInterface::VERBOSITY_QUIET];

        yield 'long form' => ['messenger:consume default --verbose', OutputInterface::VERBOSITY_VERBOSE];
    }

    public function testThatEachCommandGetsItsOwnStream(): void
    {
        $factory = new StreamCommandOutputFactory();

        $first = $factory->newOutput('a');
        $second = $factory->newOutput('b');

        self::assertInstanceOf(StreamOutput::class, $first);
        self::assertInstanceOf(StreamOutput::class, $second);

        // Separate handles: several commands write from several coroutines in one task worker, and a
        // shared handle interleaves their writes mid-line.
        self::assertNotSame($first->getStream(), $second->getStream());
    }
}

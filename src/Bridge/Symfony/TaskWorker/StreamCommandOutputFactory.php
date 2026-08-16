<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\TaskWorker;

use Override;
use RuntimeException;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;

/**
 * EXPERIMENTAL. Sends command output to the server's stdout, one stream handle per command.
 *
 * Separate handles rather than one shared StreamOutput: several commands can be writing from several
 * coroutines in the same task worker, and sharing a handle interleaves their writes mid-line.
 *
 * Verbosity is resolved here because these commands never go through Application::run(), which is what
 * normally reads -v/-q off the input and configures the output. Without this, verbosity flags written
 * in configuration would parse cleanly and then do nothing.
 */
final class StreamCommandOutputFactory implements CommandOutputFactory
{
    #[Override]
    public function newOutput(string $commandLine): OutputInterface
    {
        $stream = fopen('php://stdout', 'wb');

        if ($stream === false) {
            throw new RuntimeException('Unable to open php://stdout for task worker command output.');
        }

        $input = new StringInput($commandLine);

        return new StreamOutput(
            $stream,
            $this->verbosity($input),
            $input->hasParameterOption(['--ansi'], true) ? true : null,
        );
    }

    private function verbosity(StringInput $input): int
    {
        return match (true) {
            $input->hasParameterOption(['--quiet', '-q'], true) => OutputInterface::VERBOSITY_QUIET,
            $input->hasParameterOption('-vvv', true),
            $input->hasParameterOption('--verbose=3', true) => OutputInterface::VERBOSITY_DEBUG,
            $input->hasParameterOption('-vv', true),
            $input->hasParameterOption('--verbose=2', true) => OutputInterface::VERBOSITY_VERY_VERBOSE,
            $input->hasParameterOption('-v', true),
            $input->hasParameterOption('--verbose', true) => OutputInterface::VERBOSITY_VERBOSE,
            default => OutputInterface::VERBOSITY_NORMAL,
        };
    }
}

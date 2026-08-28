<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Command;

use Override;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Test\ServerTestCase;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Stands in for a real long running worker inside a task worker.
 *
 * It writes a file the test can read from outside the server: the pid it started on, a tick per loop,
 * and - only if it was asked to stop rather than killed - a final "stopped" line. That last line is the
 * whole point, since it is what tells a graceful shutdown apart from a force-termination at
 * max_wait_time, which leaves the file ending on a tick.
 *
 * Signals are subscribed for real. Nothing ever sends one inside a swoole worker - swoole owns them -
 * so handleSignal() firing at all is the evidence that the bundle re-delivered the stop by hand.
 */
#[AsCommand(
    name: 'test:task-worker:heartbeat',
    description: 'Long running command used to verify commands run, and stop, inside task workers.',
)]
final class TaskWorkerHeartbeatCommand extends Command
{
    public const string FILE_PREFIX = 'task-worker-heartbeat-';

    private bool $stopRequested = false;

    public static function filePath(string $slot): string
    {
        return ServerTestCase::FIXTURE_RESOURCES_DIR . DIRECTORY_SEPARATOR . self::FILE_PREFIX . $slot . '.txt';
    }

    #[Override]
    public function getSubscribedSignals(): array
    {
        return defined('SIGTERM') ? [SIGTERM] : [];
    }

    #[Override]
    public function handleSignal(int $signal, int|false $previousExitCode = 0): int|false
    {
        $this->stopRequested = true;

        return false;
    }

    #[Override]
    protected function configure(): void
    {
        $this->addArgument('slot', InputArgument::REQUIRED, 'Names the file this instance writes to')
            ->addOption('interval', null, InputOption::VALUE_REQUIRED, 'Milliseconds between ticks', '200')
            // Stands in for --memory-limit: a command that ends by itself, so its task worker is
            // recycled and the replacement has to start the command again.
            ->addOption('max-ticks', null, InputOption::VALUE_REQUIRED, 'Ticks before ending (0 = never)', '0');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $slot */
        $slot = $input->getArgument('slot');
        /** @var string $interval */
        $interval = $input->getOption('interval');
        /** @var string $maxTicks */
        $maxTicks = $input->getOption('max-ticks');
        $ticks = 0;

        $path = self::filePath($slot);
        // Written through a rename, so that a reader outside the server never catches the file between
        // its truncation and the write. file_put_contents() does those in two steps - and with the
        // coroutine file hook on, two steps with a yield between them - which is wide enough on a loaded
        // build box for a test sampling the file to read it empty. A rename cannot be observed half done.
        $startedFile = sprintf('%s.%d.tmp', $path, getmypid());
        file_put_contents($startedFile, sprintf("started pid=%d\n", getmypid()));
        rename($startedFile, $path);

        while (!$this->stopRequested) {
            // Hooked under coroutines, so this yields and lets the watchdog coroutine run; a plain
            // blocking sleep with coroutines off, where nothing else is running anyway.
            usleep(((int) $interval) * 1000);

            file_put_contents($path, "tick\n", FILE_APPEND);
            $ticks++;

            if ((int) $maxTicks > 0 && $ticks >= (int) $maxTicks) {
                break;
            }
        }

        file_put_contents($path, "stopped\n", FILE_APPEND);
        $output->writeln(sprintf('Heartbeat %s stopped.', $slot));

        return self::SUCCESS;
    }
}

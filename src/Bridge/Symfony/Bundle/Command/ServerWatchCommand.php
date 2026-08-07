<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\Command;

use Override;
use SwooleBundle\SwooleBundle\Server\Runtime\Watch\FileWatcher;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\SignalableCommandInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

final class ServerWatchCommand extends Command implements SignalableCommandInterface
{
    private const int RESTART_SETTLE_US = 1_000_000;

    private const int MAX_CONSECUTIVE_FAILURES = 30;

    private ?Process $server = null;

    private bool $stopping = false;

    /**
     * @var array<string>
     */
    private array $serverArgs = [];

    public function __construct(
        private readonly string $projectDir,
        private readonly string $kernelEnvironment,
        private readonly bool $kernelDebug,
    ) {
        parent::__construct();
    }

    /**
     * @return array<int>
     */
    #[Override]
    public function getSubscribedSignals(): array
    {
        $signals = [];
        if (defined('SIGTERM')) {
            $signals[] = SIGTERM;
        }
        if (defined('SIGINT')) {
            $signals[] = SIGINT;
        }

        return $signals;
    }

    /** @phpstan-ignore return.unusedType */
    #[Override]
    public function handleSignal(int $signal, int|false $previousExitCode = 0): int|false
    {
        $this->stopping = true;
        $this->stopServer();

        return 0;
    }

    #[Override]
    protected function configure(): void
    {
        $this
            ->setDescription('Run the Swoole HTTP server with dev auto-reload (full restart on any watched change).')
            ->addOption(
                'path',
                null,
                InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED,
                'Directory to watch, relative to the project dir (repeatable)',
                ['src', 'config'],
            )
            ->addOption('interval', null, InputOption::VALUE_REQUIRED, 'Poll interval in milliseconds', '1000')
            ->addArgument(
                'server-args',
                InputArgument::IS_ARRAY | InputArgument::OPTIONAL,
                'Arguments forwarded verbatim to "swoole:server:run", passed after "--". '
                . 'Example: swoole:server:watch -- --host=0.0.0.0 --port=9501 --api',
            );
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var array<string> $relativePaths */
        $relativePaths = $input->getOption('path');
        $paths = array_map(fn(string $path): string => $this->projectDir . '/' . ltrim($path, '/'), $relativePaths);
        /** @var string $intervalOption */
        $intervalOption = $input->getOption('interval');
        $intervalMs = max(100, (int) $intervalOption);
        /** @var array<string> $serverArgs */
        $serverArgs = $input->getArgument('server-args');
        $this->serverArgs = $serverArgs;

        $watcher = new FileWatcher($paths);

        $this->server = $this->startServer($output);
        $io->writeln(sprintf(
            '[watch] supervising "swoole:server:run%s" (env %s, debug %s); restarting on any change in %s (poll %dms).',
            $this->serverArgs === [] ? '' : ' ' . implode(' ', $this->serverArgs),
            $this->kernelEnvironment,
            $this->kernelDebug ? 'on' : 'off',
            implode(', ', $relativePaths),
            $intervalMs,
        ));

        $previous = $watcher->snapshot();
        $consecutiveFailures = 0;
        /** @var array<string, int>|null $lintBlocked */
        $lintBlocked = null;

        while (!$this->stopping) {
            if (!$this->waitInterval($intervalMs)) {
                ++$consecutiveFailures;

                if ($consecutiveFailures > self::MAX_CONSECUTIVE_FAILURES) {
                    $io->error('[watch] server repeatedly failed to stay up; giving up.');

                    break;
                }

                $io->warning('[watch] server is not running — restarting.');
                $this->restartServer($output);
                $previous = $watcher->snapshot();
                $lintBlocked = null;

                continue;
            }

            $consecutiveFailures = 0;

            $current = $watcher->snapshot();
            if ($lintBlocked === $current) {
                continue;
            }

            $change = $watcher->classify($previous, $current);

            if (!$change->hasChanges) {
                $lintBlocked = null;

                continue;
            }

            if (!$this->lint($change->lintTargets, $io)) {
                // Keep $previous unchanged so fixing the syntax error re-triggers the restart.
                $lintBlocked = $current;

                continue;
            }

            $lintBlocked = null;

            $io->writeln(sprintf('[watch] %s — restarting server', $change->reason));
            $this->restartServer($output);
            $previous = $watcher->snapshot();
        }

        $this->stopServer();

        return self::SUCCESS;
    }

    private function startServer(OutputInterface $output): Process
    {
        $process = new Process(
            [PHP_BINARY, $this->projectDir . '/bin/console', 'swoole:server:run', ...$this->serverArgs],
            $this->projectDir,
            [
                'APP_ENV' => $this->kernelEnvironment,
                'APP_DEBUG' => $this->kernelDebug ? '1' : '0',
            ],
        );
        $process->setTimeout(null);
        $process->start(static function (string $type, string $buffer) use ($output): void {
            if ($type === Process::ERR && $output instanceof ConsoleOutputInterface) {
                $output->getErrorOutput()->write($buffer);

                return;
            }

            $output->write($buffer);
        });

        return $process;
    }

    private function stopServer(): void
    {
        if ($this->server === null) {
            return;
        }

        if ($this->server->isRunning()) {
            $this->server->stop(10.0);
        }

        $this->server = null;
    }

    private function restartServer(OutputInterface $output): void
    {
        $this->stopServer();
        usleep(self::RESTART_SETTLE_US);
        $this->server = $this->startServer($output);
    }

    private function waitInterval(int $intervalMs): bool
    {
        $deadline = microtime(true) + ($intervalMs / 1000);
        $chunkUs = (int) min(50_000, $intervalMs * 1000);

        do {
            if ($this->server === null || !$this->server->isRunning()) {
                return false;
            }

            usleep($chunkUs);
        } while (microtime(true) < $deadline);

        return true;
    }

    /**
     * @param array<string> $files
     */
    private function lint(array $files, SymfonyStyle $io): bool
    {
        $ok = true;
        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }

            $lint = new Process([PHP_BINARY, '-l', $file]);
            $lint->run();

            if ($lint->isSuccessful()) {
                continue;
            }

            $io->warning(sprintf('[watch] syntax error in %s — keeping current server, not restarting', $file));
            $ok = false;
        }

        return $ok;
    }
}

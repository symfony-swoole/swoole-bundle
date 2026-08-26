<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\Command;

use Override;
use SwooleBundle\SwooleBundle\Server\Runtime\HMR\ContainerFreshness;
use SwooleBundle\SwooleBundle\Server\Runtime\Watch\FileWatcher;
use SwooleBundle\SwooleBundle\Server\Runtime\Watch\ProcessStopper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\SignalableCommandInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

final class ServerWatchCommand extends Command implements SignalableCommandInterface
{
    private const int RESTART_SETTLE_US = 1_000_000;

    private const int MAX_CONSECUTIVE_FAILURES = 30;

    /**
     * How long the server is given to stop before it is taken apart by force.
     *
     * It has to be longer than the server's own shutdown budget, and the old two seconds were shorter
     * than even swoole's default `max_wait_time` of three. Under load - workers mid-request - the
     * supervisor therefore killed the master while the manager and the workers were still winding
     * down. They were reparented to init, kept the listen socket, and the next server could not bind
     * the port. Raise it further with --stop-timeout wherever `worker_max_wait_time` is raised.
     */
    private const float STOP_TIMEOUT_S = 15.0;

    /**
     * Where a Symfony application keeps its console, and where this looks unless told otherwise.
     */
    private const string DEFAULT_CONSOLE = 'bin/console';

    private ?Process $server = null;

    private bool $stopping = false;

    /**
     * @var array<string>
     */
    private array $serverArgs = [];

    private string $console = self::DEFAULT_CONSOLE;

    private float $stopTimeout = self::STOP_TIMEOUT_S;

    private ?SymfonyStyle $io = null;

    public function __construct(
        private readonly string $projectDir,
        private readonly string $kernelEnvironment,
        private readonly bool $kernelDebug,
        private readonly string $cacheDir,
        private readonly ContainerFreshness $freshness,
        private readonly Filesystem $filesystem,
        private readonly ProcessStopper $stopper = new ProcessStopper(),
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
            ->addOption(
                'stop-timeout',
                null,
                InputOption::VALUE_REQUIRED,
                'Seconds the server is given to stop before it is killed. Must be longer than the '
                . 'server\'s own "worker_max_wait_time", or busy workers are killed mid-request and '
                . 'leave the port behind them.',
                (string) self::STOP_TIMEOUT_S,
            )
            ->addOption(
                'console',
                null,
                InputOption::VALUE_REQUIRED,
                'The console to start the server with, absolute or relative to the project dir. '
                . 'For an application whose console is not at the usual place.',
                self::DEFAULT_CONSOLE,
            )
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
        /** @var string $console */
        $console = $input->getOption('console');
        $this->console = $console;
        /** @var string $stopTimeout */
        $stopTimeout = $input->getOption('stop-timeout');
        $this->stopTimeout = max(1.0, (float) $stopTimeout);
        $this->io = $io;

        if (!is_file($this->consolePath())) {
            $io->error(sprintf(
                'No console at "%s". Pass --console with the path to this application\'s console.',
                $this->consolePath(),
            ));

            return self::FAILURE;
        }

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
                if ($this->stopping) {
                    break;
                }

                $current = $watcher->snapshot();
                if ($lintBlocked === $current) {
                    usleep($intervalMs * 1000);

                    continue;
                }

                $change = $watcher->classify($previous, $current);

                if ($change->hasChanges && !$this->lint($change->lintTargets, $io)) {
                    $io->warning('[watch] server is DOWN — fix the error above and save to restart it.');
                    $lintBlocked = $current;
                    usleep($intervalMs * 1000);

                    continue;
                }

                ++$consecutiveFailures;

                if ($consecutiveFailures > self::MAX_CONSECUTIVE_FAILURES) {
                    $io->error('[watch] server repeatedly failed to stay up; giving up.');

                    break;
                }

                $io->warning('[watch] server is not running — restarting.');
                $previous = $current;
                $lintBlocked = null;
                $this->restartServer($output);

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
            $previous = $current;
            $this->restartServer($output);
        }

        $this->stopServer();

        return self::SUCCESS;
    }

    private function startServer(OutputInterface $output): Process
    {
        $process = new Process(
            [PHP_BINARY, $this->consolePath(), 'swoole:server:run', ...$this->serverArgs],
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

    /**
     * An absolute path is taken as given; anything else is read from the project dir, which is where
     * "bin/console" and every variation on it lives.
     */
    private function consolePath(): string
    {
        if (str_starts_with($this->console, '/')) {
            return $this->console;
        }

        return $this->projectDir . '/' . ltrim($this->console, '/');
    }

    /**
     * Stops the server, and makes sure nothing of it is left behind.
     *
     * @see ProcessStopper for why stopping the master is not the same as stopping the server
     */
    private function stopServer(): void
    {
        $server = $this->server;
        $this->server = null;

        if ($server === null) {
            return;
        }

        $killed = $this->stopper->stop($server, $this->stopTimeout);

        if ($killed === []) {
            return;
        }

        $this->io?->warning(sprintf(
            '[watch] the server did not stop within %.0fs and left %d process(es) behind; they have '
            . 'been killed. Raise --stop-timeout above the server\'s worker_max_wait_time to let it '
            . 'shut down on its own.',
            $this->stopTimeout,
            count($killed),
        ));
    }

    private function restartServer(OutputInterface $output): void
    {
        $this->stopServer();
        $this->clearCacheIfContainerWentStale($output);
        usleep(self::RESTART_SETTLE_US);
        $this->server = $this->startServer($output);
    }

    /**
     * Drops the cache directory when the change that triggered this restart was one the compiled
     * container was built from.
     *
     * Only then, because it is not free - the next boot pays for compiling the container again, and
     * most restarts here are a changed class body that the container never recorded anything about.
     *
     * Deliberately not left to the kernel. A debug kernel does check its container's freshness on boot
     * and rebuilds a stale one, so this looks redundant - but only for the container. Everything else
     * in the directory, the pools and the warmed caches, has no such check and is simply reused. And
     * with debug off the container has no freshness check either: ConfigCache without debug answers
     * fresh for any file that exists, so the restarted server would go on running the old container
     * indefinitely.
     *
     * After stopServer(), so nothing is reading the directory while it goes.
     */
    private function clearCacheIfContainerWentStale(OutputInterface $output): void
    {
        if (!$this->freshness->canTell()) {
            // No compiled container yet, or one built without the record of what it came from. The
            // restart still happens; there is simply nothing to decide from.
            return;
        }

        if (!$this->freshness->isStale()) {
            return;
        }

        $output->writeln(sprintf('[watch] container is stale — clearing %s', $this->cacheDir));

        $this->filesystem->remove($this->cacheDir);
    }

    /** @phpstan-impure the signal handler can flip $stopping and drop the server while this sleeps */
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

            $io->warning(sprintf('[watch] syntax error in %s — not restarting until it compiles', $file));
            $ok = false;
        }

        return $ok;
    }
}

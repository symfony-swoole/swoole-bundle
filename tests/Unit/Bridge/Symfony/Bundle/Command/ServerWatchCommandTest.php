<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Symfony\Bundle\Command;

use Override;
use PHPUnit\Framework\TestCase;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\Command\ServerWatchCommand;
use Symfony\Component\Console\Tester\CommandTester;

final class ServerWatchCommandTest extends TestCase
{
    private const string STUB_PRELUDE = <<<'PHP'
        <?php
        $project = dirname(__DIR__);
        $runs = 1 + (int) @file_get_contents($project . '/runs');
        file_put_contents($project . '/runs', (string) $runs);
        $watched = $project . '/src/App.php';
        $writeWatched = static function (string $code) use ($watched): void {
            file_put_contents($watched, $code);
            touch($watched, 1_700_000_000);
        };
        $watchPid = posix_getppid();
        $stopWatcher = static fn(): bool => posix_kill($watchPid, SIGUSR1);

        PHP;

    private const string BROKEN_PHP = "<?php\nfinal class App { public function broken(: void {} }\n";

    private const string VALID_PHP = "<?php\nfinal class App { public function fine(): void {} }\n";

    private string $projectDir;

    #[Override]
    protected function setUp(): void
    {
        if (!function_exists('pcntl_signal') || !function_exists('posix_kill')) {
            self::markTestSkipped('ext-pcntl and ext-posix are required to interrupt the watch loop.');
        }

        $this->projectDir = sys_get_temp_dir() . '/watch_' . uniqid('', true);
        mkdir($this->projectDir . '/bin', 0o777, true);
        mkdir($this->projectDir . '/src', 0o777, true);
        file_put_contents($this->projectDir . '/src/App.php', self::VALID_PHP);
    }

    #[Override]
    protected function tearDown(): void
    {
        foreach (['/bin/console', '/src/App.php', '/runs'] as $file) {
            @unlink($this->projectDir . $file);
        }

        @rmdir($this->projectDir . '/bin');
        @rmdir($this->projectDir . '/src');
        @rmdir($this->projectDir);
    }

    public function testSubscribesToTerminationSignals(): void
    {
        $command = new ServerWatchCommand($this->projectDir, 'dev', true);

        self::assertSame([SIGTERM, SIGINT], $command->getSubscribedSignals());
        self::assertSame(0, $command->handleSignal(SIGTERM));
    }

    public function testRestartsServerOnWatchedFileChange(): void
    {
        $this->writeConsoleStub(<<<'PHP'
            fwrite(STDOUT, sprintf("server %d up\n", $runs));

            if ($runs === 1) {
                usleep(300_000);
                $writeWatched('<?php final class App { public function added(): void {} }');
            } else {
                $stopWatcher();
            }

            while (true) {
                sleep(1);
            }
            PHP);

        $display = $this->runWatch();

        self::assertSame(2, $this->runs());
        self::assertStringContainsString('modified', $display);
        self::assertStringContainsString('restarting server', $display);
        self::assertStringContainsString('server 1 up', $display, 'child output must be forwarded');
        self::assertStringContainsString('server 2 up', $display);
    }

    public function testRestartsServerThatDiedOnItsOwn(): void
    {
        $this->writeConsoleStub(<<<'PHP'
            if ($runs >= 2) {
                $stopWatcher();
            }

            exit(1);
            PHP);

        $display = $this->runWatch();

        self::assertSame(2, $this->runs());
        self::assertStringContainsString('server is not running', $display);
    }

    public function testCrashedServerIsNotRestartedWhileTheChangeDoesNotCompile(): void
    {
        $this->writeConsoleStub(sprintf(<<<'PHP'
            if ($runs === 1 && pcntl_fork() === 0) {
                for ($i = 1; $i <= 8; ++$i) {
                    usleep(250_000);
                    file_put_contents($watched, %s);
                    touch($watched, 1_700_000_000 + $i);
                }

                $stopWatcher();

                exit(0);
            }

            exit(1);
            PHP, var_export(self::BROKEN_PHP, true)));

        $display = $this->runWatch(safetyTimeout: 5);

        self::assertLessThanOrEqual(2, $this->runs(), 'the syntax error must not be restarted into');
        self::assertStringContainsString('syntax error in', $display);
        self::assertStringContainsString('server is DOWN', $display);
    }

    public function testRunningServerIsKeptWhenTheChangeDoesNotCompile(): void
    {
        $this->writeConsoleStub(sprintf(<<<'PHP'
            if ($runs === 1) {
                usleep(300_000);
                $writeWatched(%s);
            }

            while (true) {
                sleep(1);
            }
            PHP, var_export(self::BROKEN_PHP, true)));

        $display = $this->runWatch(safetyTimeout: 2);

        self::assertSame(1, $this->runs());
        self::assertStringContainsString('syntax error in', $display);
        self::assertStringNotContainsString('restarting server', $display);
    }

    private function writeConsoleStub(string $body): void
    {
        file_put_contents($this->projectDir . '/bin/console', self::STUB_PRELUDE . $body);
    }

    private function runs(): int
    {
        return (int) @file_get_contents($this->projectDir . '/runs');
    }

    private function runWatch(int $safetyTimeout = 5): string
    {
        $command = new ServerWatchCommand($this->projectDir, 'dev', true);
        $stop = static function () use ($command): void {
            $command->handleSignal(SIGTERM);
        };

        pcntl_async_signals(true);
        pcntl_signal(SIGUSR1, $stop);
        pcntl_signal(SIGALRM, $stop);
        pcntl_alarm($safetyTimeout);

        $tester = new CommandTester($command);

        try {
            $tester->execute(['--interval' => '100', '--path' => ['src']]);
        } finally {
            pcntl_alarm(0);
            pcntl_signal(SIGUSR1, SIG_DFL);
            pcntl_signal(SIGALRM, SIG_DFL);
        }

        self::assertSame(ServerWatchCommand::SUCCESS, $tester->getStatusCode());

        return (string) preg_replace('/\s+/', ' ', $tester->getDisplay());
    }
}

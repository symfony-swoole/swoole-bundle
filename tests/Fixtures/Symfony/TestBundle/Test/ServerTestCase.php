<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Test;

use Assert\Assertion;
use DateTimeImmutable;
use Exception;
use Override;
use RuntimeException;
use SwooleBundle\SwooleBundle\Client\HttpClient;
use SwooleBundle\SwooleBundle\Common\Adapter\Swoole;
use SwooleBundle\SwooleBundle\Common\System\System;
use SwooleBundle\SwooleBundle\Coroutine\CoroutinePool;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestAppKernel;
use SwooleBundle\SwooleBundle\Tests\Helper\ConsoleProcess;
use SwooleBundle\SwooleBundle\Tests\Helper\SwooleFactoryFactory;
use SwooleBundle\SwooleBundle\Tests\Helper\TestDatabase;
use SwooleBundle\SwooleBundle\Tests\Helper\TestToken;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Throwable;

abstract class ServerTestCase extends KernelTestCase
{
    final public const string FIXTURE_RESOURCES_DIR = __DIR__ . '/../../../resources';
    private const string COMMAND = './console';
    private const string WORKING_DIRECTORY = __DIR__ . '/../../app';

    /**
     * The commands that write - or read back - the pid of a daemonized server, and so have to be pointed
     * at the current worker's pid file.
     */
    private const array PID_FILE_AWARE_COMMANDS = [
        'swoole:server:start',
        'swoole:server:stop',
        'swoole:server:reload',
    ];

    protected ?Swoole $swoole = null;

    /**
     * @var callable(Throwable): void|null
     */
    private $previousHandler;

    /**
     * A failure from the deferred server stop, held back until tearDown so it cannot mask the test.
     */
    private ?Throwable $deferredServerStopFailure = null;

    #[Override]
    protected function setUp(): void
    {
        TestDatabase::ensureExists();
        $this->exportWorkerEnvironment();
        $this->claimTestPorts();

        // Capture the existing handler while setting your mock/test handler
        $this->previousHandler = set_exception_handler(static function ($e): void {});
    }

    #[Override]
    protected function tearDown(): void
    {
        parent::tearDown();

        // Make sure everything is stopped
        $this->releaseTestPorts();

        // Restore the original handler
        set_exception_handler($this->previousHandler);

        $stopFailure = $this->deferredServerStopFailure;
        $this->deferredServerStopFailure = null;

        // A stop that failed on its own is worth failing the test for - but only then. PHPUnit has
        // already settled the status by the time tearDown runs, and anything thrown from here overwrites
        // it, so a test that failed for a reason of its own keeps that reason.
        if ($stopFailure === null || !$this->status()->isSuccess()) {
            return;
        }

        throw $stopFailure;
    }

    /**
     * The n-th port of the ones this test worker owns.
     *
     * Feature tests address ports by offset rather than by number, so that workers running side by side
     * never bind the same one. Offset 0 is the port a serial run has always used - 9999.
     *
     * @param int<0, max> $offset
     */
    public static function port(int $offset = 0): int
    {
        return TestToken::port($offset);
    }

    /**
     * How long a client waits for a server that may still be booting.
     *
     * Three seconds is what one server needs to come up on an otherwise idle machine, which is what the
     * tests were written against. A worker sharing a build box with three others needs longer, and it
     * needs it in the same proportion the console processes do - so the wait is stretched by the factor
     * rather than raised across the board, and a serial run still gives up after the three seconds it
     * always did.
     */
    public static function connectTimeout(int $seconds = 3): int
    {
        return (int) ceil($seconds * TestToken::timeoutFactor());
    }

    public static function resolveEnvironment(?string $env = null): string
    {
        if (self::coverageEnabled()) {
            if ($env === 'test' || $env === null) {
                $env = 'cov';
            } elseif (mb_substr($env, -4, 4) !== '_cov') {
                $env .= '_cov';
            }
        }

        return $env ?? 'test';
    }

    public static function coverageEnabled(): bool
    {
        return getenv('COVERAGE') !== false;
    }

    public function runAsCoroutineAndWait(callable $callable): void
    {
        $coroutinePool = CoroutinePool::fromCoroutines($callable);

        try {
            $coroutinePool->run();
        } catch (RuntimeException $runtimeException) {
            throw $runtimeException;
        }
    }

    /**
     * Notice: This command requires running on os with "lsof" binary that supports "-i :PORT" option
     *         For example for alpine it is required to install it via: apk add lsof.
     */
    public function killAllProcessesListeningOnPort(int $port, int $timeout = 1): void
    {
        $this->killAllProcessesListeningOnPorts([$port], $timeout);
    }

    /**
     * @param non-empty-list<int> $ports
     */
    public function killAllProcessesListeningOnPorts(array $ports, int $timeout = 1): void
    {
        foreach ($this->processesListeningOnPorts($ports, $timeout) as $processId) {
            $kill = new Process(['kill', '-9', $processId]);
            $kill->setTimeout($timeout);
            $kill->disableOutput();
            $kill->run();
        }
    }

    public function killProcessUsingSignal(int $pid, int $signal = SIGTERM, int $timeout = 1): void
    {
        $kill = new Process(['kill', sprintf('-%d', $signal), $pid]);
        $kill->setTimeout($timeout);
        $kill->disableOutput();
        $kill->run();
    }

    public function assertProcessSucceeded(Process $process): void
    {
        $status = $process->isSuccessful();

        if (!$status) {
            throw new ProcessFailedException($process);
        }
    }

    public function assertCommandTesterDisplayContainsString(string $expected, CommandTester $commandTester): void
    {
        self::assertStringContainsString(
            $expected,
            preg_replace('!\s+!', ' ', str_replace(PHP_EOL, '', $commandTester->getDisplay()))
        );
    }

    /**
     * @param array<string, string> $args
     * @param array<string, string> $envs
     */
    public function deferServerStop(array $args = [], array $envs = []): void
    {
        defer(function () use ($args, $envs): void {
            // Nothing may be thrown from here. This runs as the coroutine unwinds, where an exception
            // becomes an uncaught fatal and takes the place of whatever the test was about to report -
            // so a server that died mid-test surfaces as "stop failed: the server has not been running",
            // and the reason it died, which the test had an assertion and a message ready for, is lost.
            // tearDown() raises this instead, and only when nothing better has already failed.
            try {
                $this->serverStop($args, $envs);
            } catch (Throwable $throwable) {
                $this->deferredServerStopFailure ??= $throwable;
            }
        });
    }

    /**
     * @param array<string> $args
     * @param array<string, string> $envs
     */
    public function serverStop(array $args = [], array $envs = []): void
    {
        /** @var array<string, string> $processArgs */
        $processArgs = array_merge(['swoole:server:stop'], $args);
        $serverStop = $this->createConsoleProcess($processArgs, $envs);

        $serverStop->setTimeout(10);
        $serverStop->run();

        $this->assertProcessSucceeded($serverStop);
        self::assertStringContainsString('Swoole server shutdown successfully', $serverStop->getOutput());
    }

    /**
     * @param array<string> $args
     * @param array<string, string> $envs
     */
    public function createConsoleProcess(
        array $args,
        array $envs = [],
        mixed $input = null,
        ?float $timeout = 60.0,
    ): Process {
        $command = array_merge([self::COMMAND], $this->withWorkerPidFile($args));
        $referenceFile = realpath(__DIR__ . '/../../app/config/reference.php');

        if (!isset($envs['APP_RUNTIME_MODE'])) {
            $envs['APP_RUNTIME_MODE'] = 'web=1&worker=1';
        }

        // The fixture app reads its database from here, so that a worker migrating and dropping its
        // schema cannot reach into a sibling's. Outside ParaTest this is the "db" it always was.
        if (!isset($envs['DATABASE_NAME'])) {
            $envs['DATABASE_NAME'] = TestToken::databaseName();
        }

        // The health process binds a second socket, configured rather than passed on the command line.
        if (!isset($envs['HEALTH_PORT'])) {
            $envs['HEALTH_PORT'] = (string) self::port(2);
        }

        if (is_string($referenceFile) && file_exists($referenceFile)) {
            // the reference.php file is not compatible with phpunit process isolation
            // Parallel workers race each other to it; losing the race is not worth a warning.
            @unlink($referenceFile);
        }

        return new ConsoleProcess(
            $command,
            (string) realpath(self::WORKING_DIRECTORY),
            $envs,
            $input,
            $timeout
        );
    }

    public function assertHelloWorldRequestSucceeded(HttpClient $client): void
    {
        $response = $client->send('/')['response'];

        self::assertSame(200, $response['statusCode']);
        self::assertSame([
            'hello' => 'world!',
        ], $response['body']);
    }

    public function assertProcessFailed(Process $process): void
    {
        self::assertFalse($process->isSuccessful());
    }

    /**
     * @param array{
     *   environment?: string,
     *   debug?: bool,
     *   override_prod_env?: string,
     * } $options
     */
    // phpcs:disable SlevomatCodingStandard.Variables.DisallowSuperGlobalVariable.DisallowedSuperGlobalVariable
    #[Override]
    protected static function createKernel(array $options = []): KernelInterface
    {
        if (static::$class === null) {
            static::$class = static::getKernelClass();
        }

        $options['environment'] = self::resolveEnvironment($options['environment'] ?? null);
        $env = $options['environment'];

        if (isset($options['debug'])) {
            $debug = $options['debug'];
        } elseif (isset($_ENV['APP_DEBUG'])) {
            $debug = $_ENV['APP_DEBUG'];
        } elseif (isset($_SERVER['APP_DEBUG'])) {
            $debug = $_SERVER['APP_DEBUG'];
        } else {
            $debug = true;
        }

        $overrideProdEnv = null;

        if (isset($options['override_prod_env'])) {
            $overrideProdEnv = $options['override_prod_env'];
        } elseif (isset($_SERVER['OVERRIDE_PROD_ENV'])) {
            $overrideProdEnv = $_SERVER['OVERRIDE_PROD_ENV'];
        }

        $kernel = new static::$class($env, $debug, $overrideProdEnv);

        Assertion::isInstanceOf($kernel, KernelInterface::class, 'Kernel class must implement KernelInterface');

        return $kernel;
    }

    #[Override]
    protected static function getKernelClass(): string
    {
        return TestAppKernel::class;
    }

    protected function getSwoole(): Swoole
    {
        if ($this->swoole === null) {
            $this->swoole = SwooleFactoryFactory::newInstance();
        }

        return $this->swoole;
    }

    /**
     * Skips a test that only the swoole extension fails, so the reason travels with it.
     *
     * Reach for this when a failure has been traced to a difference between the extensions rather than
     * to the test - and say which difference in $reason, because a skip that outlives its explanation
     * is how a known bug turns into an unknown one.
     */
    protected function markTestSkippedOnSwoole(string $reason): void
    {
        if (System::create()->extension()->isOpenSwoole()) {
            return;
        }

        self::markTestSkipped($reason);
    }

    protected function markTestSkippedIfInotifyDisabled(): void
    {
        if (extension_loaded('inotify')) {
            return;
        }

        self::markTestSkipped(
            'Swoole Bundle HMR requires "inotify" PHP extension present and installed on the system.'
        );
    }

    protected function markTestSkippedIfSymfonyVersionIsLoverThan(string $version): void
    {
        if (!version_compare(Kernel::VERSION, $version, 'lt')) {
            return;
        }

        self::markTestSkipped(sprintf('This test needs Symfony in version : %s.', $version));
    }

    /**
     * @param int<1, max> $factor
     */
    protected function generateUniqueHash(int $factor = 8): string
    {
        try {
            return bin2hex(random_bytes($factor));
        } catch (Exception) {
            $array = range(1, $factor * 2);
            shuffle($array);

            return implode('', $array);
        }
    }

    protected function currentUnixTimestamp(): int
    {
        return (new DateTimeImmutable())->getTimestamp();
    }

    /**
     * Sends a single request to the health endpoint over a connection of its own.
     *
     * The health process answers exactly one request per connection and then closes it - it says so with
     * `Connection: close` and calls fclose() right after writing the response (see WithHealthProcess).
     * A client can therefore never be reused for a second request: the next request races the close, and
     * depending on whether the client notices it first, it either silently reconnects or dies with
     * "Server Reset". That is why reusing one client here fails intermittently rather than always.
     *
     * @return array{statusCode: int, body: array<string, mixed>, headers: array<string, string>}
     */
    protected function sendHealthRequest(int $port, string $path, int $timeout = 3): array
    {
        /** @var array{statusCode: int, body: array<string, mixed>, headers: array<string, string>} $response */
        $response = HttpClient::fromDomain('localhost', $port, false)
            ->send($path, timeout: $timeout)['response'];

        return $response;
    }

    /**
     * Waits until the health process has bound its socket - it does so only after the start command has
     * already returned. The client used for the probe is deliberately thrown away afterwards, because
     * the probe itself consumes its connection.
     */
    protected function awaitHealthEndpoint(int $port): bool
    {
        return HttpClient::fromDomain('localhost', $port, false)->connect(self::connectTimeout(), 1, true);
    }

    protected function deleteVarDirectory(): void
    {
        $varDirectory = $this->getVarDirectoryPath();

        $fs = new Filesystem();
        $fs->remove([
            $varDirectory . '/cache',
            $varDirectory . '/log',
            $varDirectory . '/swoole.pid',
        ]);

        // Swoole's own logger opens its file and does not create the directory for it, so a server
        // started after this ran would report "Logger::open() failed. No such file or directory" and
        // then write its diagnostics nowhere. That is worth more than it sounds: when a server dies
        // during boot, that log is the only place its exception is recorded.
        $fs->mkdir($varDirectory . '/log');
    }

    /**
     * The fixture app's var directory for this worker - "var" on its own, "var-2" for the second
     * ParaTest worker and so on. TestAppKernel derives the same path, so the kernel booted in the
     * console process this test spawns caches into the very directory the test watches.
     */
    protected function getVarDirectoryPath(): string
    {
        return self::WORKING_DIRECTORY . '/var' . TestToken::suffix();
    }

    /**
     * Publishes the worker's share of the shared resources as environment variables.
     *
     * Feature tests reach the fixture app two ways - through a console process they spawn, and through
     * a kernel they boot in-process to inspect services or query the database. Both have to resolve
     * %env(DATABASE_NAME)% and friends the same way, so the values go into this process's environment
     * rather than only into the child's.
     */
    private function exportWorkerEnvironment(): void
    {
        $variables = [
            'DATABASE_NAME' => TestToken::databaseName(),
            'HEALTH_PORT' => (string) self::port(2),
        ];

        foreach ($variables as $name => $value) {
            $_ENV[$name] = $_SERVER[$name] = $value;
            putenv(sprintf('%s=%s', $name, $value));
        }
    }

    /**
     * Points the server commands at this worker's pid file, unless the test cares about the pid file
     * itself and named one already.
     *
     * The commands default it to "<project dir>/var/swoole.pid" - a path that is one and the same for
     * every worker, which would have them stopping and reloading each other's servers.
     *
     * @param array<string> $args
     * @return array<string>
     */
    private function withWorkerPidFile(array $args): array
    {
        if ($args === [] || !in_array($args[0], self::PID_FILE_AWARE_COMMANDS, true)) {
            return $args;
        }

        foreach ($args as $arg) {
            if (str_starts_with($arg, '--pid-file')) {
                return $args;
            }
        }

        $args[] = sprintf('--pid-file=%s/swoole.pid', $this->getVarDirectoryPath());

        return $args;
    }

    /**
     * Takes this worker's ports before the test starts using them.
     *
     * A run killed halfway through - a cancelled CI job, a `q` at the debugger - leaves servers behind,
     * and because a worker's ports are the same from run to run they are exactly the ports the next run
     * needs. Whatever is still holding them outlived the test that started it, so it gets no grace here:
     * the alternative is a bind failure that reads as "address in use" with nothing to point at.
     */
    private function claimTestPorts(): void
    {
        $this->killServersOfThisWorker();
        $this->waitUntilTestPortsAreFree(2.0);
    }

    /**
     * Leaves this worker's ports free for whatever runs next.
     *
     * Tests stop the servers they start, so the usual outcome is that nothing is listening any more by
     * the time this runs and it returns at once - which is the point, the fixed sleep it replaced cost
     * a second on every single test.
     */
    private function releaseTestPorts(): void
    {
        // Let a server that is shutting down of its own accord finish - under coverage it still has a
        // .cov file to write, and killing it halfway through loses the test's coverage.
        $this->waitUntilTestPortsAreFree(self::coverageEnabled() ? 3.0 : 1.0);

        $this->killServersOfThisWorker();
        $this->waitUntilTestPortsAreFree(2.0);
    }

    /**
     * Kills every server this worker started that is still alive.
     *
     * Two ways of finding them, because neither alone is enough. Whoever holds one of the worker's
     * ports is the obvious one. The other is anything whose command line carries one of those ports or
     * this worker's pid file: `swoole:server:start` daemonizes, so it reports success the moment the
     * daemon is forked - seconds before that daemon has booted a kernel and bound anything. A test that
     * fails in between leaves a daemon no socket points at yet, which then takes the port out from
     * under the next test. The command line is there from the first instant; the socket is not.
     */
    private function killServersOfThisWorker(): void
    {
        $processIds = array_unique(array_merge(
            $this->processesListeningOnPorts(TestToken::ports()),
            $this->processIdsStartedByThisWorker(),
        ));

        foreach ($processIds as $processId) {
            $kill = new Process(['kill', '-9', $processId]);
            $kill->setTimeout(1);
            $kill->disableOutput();
            $kill->run();
        }
    }

    /**
     * @return list<string>
     */
    private function processIdsStartedByThisWorker(): array
    {
        $markers = [sprintf('--pid-file=%s/swoole.pid', $this->getVarDirectoryPath())];

        foreach (TestToken::ports() as $port) {
            $markers[] = sprintf('--port=%d', $port);
            $markers[] = sprintf('--api-port=%d', $port);
        }

        $listProcesses = new Process(['ps', '-eo', 'pid=,args=']);
        $listProcesses->setTimeout(1);
        $listProcesses->run();

        $processIds = [];

        foreach (explode(PHP_EOL, $listProcesses->getOutput()) as $line) {
            if (!str_contains($line, self::COMMAND . ' swoole:server:')) {
                continue;
            }

            foreach ($markers as $marker) {
                if (!str_contains($line, $marker)) {
                    continue;
                }

                $processIds[] = (string) (int) trim($line);

                break;
            }
        }

        return $processIds;
    }

    private function waitUntilTestPortsAreFree(float $timeout): bool
    {
        $deadline = microtime(true) + $timeout;

        while (true) {
            if ($this->testPortsAreBindable()) {
                return true;
            }

            if (microtime(true) >= $deadline) {
                return false;
            }

            usleep(50_000);
        }
    }

    /**
     * Asks the kernel the only question that matters - can the server about to start take these ports?
     *
     * lsof answers a different one. It lists sockets it is allowed to see, which leaves out those held
     * by another user (the user_group fixture runs its workers as one), and it says nothing about a
     * port that a socket in TIME_WAIT would refuse. Binding it is the test.
     */
    private function testPortsAreBindable(): bool
    {
        foreach (TestToken::ports() as $port) {
            $socket = @stream_socket_server(sprintf('tcp://0.0.0.0:%d', $port), $errorCode, $errorMessage);

            if ($socket === false) {
                return false;
            }

            fclose($socket);
        }

        return true;
    }

    /**
     * @param non-empty-list<int> $ports
     * @return list<string>
     */
    private function processesListeningOnPorts(array $ports, int $timeout = 1): array
    {
        $command = ['lsof', '-t'];

        foreach ($ports as $port) {
            $command[] = '-i';
            $command[] = sprintf(':%d', $port);
        }

        $listProcesses = new Process($command);
        $listProcesses->setTimeout($timeout);
        $listProcesses->run();

        if (!$listProcesses->isSuccessful()) {
            return [];
        }

        return array_values(array_filter(explode(PHP_EOL, trim($listProcesses->getOutput()))));
    }
}

<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Feature;

use Override;
use Swoole\Coroutine;
use SwooleBundle\SwooleBundle\Client\HttpClient;
use SwooleBundle\SwooleBundle\Server\Runtime\Watch\ProcessStopper;
use SwooleBundle\SwooleBundle\Server\Runtime\Watch\ProcessTree;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Test\ServerTestCase;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * What is left running after a swoole server is stopped.
 *
 * A server is a tree - master, manager, workers - and whoever started it started only the master.
 * Process::stop() signals that master and nothing else, which is enough while the master exits on the
 * SIGTERM and takes the rest with it. When it does not, Process::stop() sends SIGKILL at the end of its
 * timeout. SIGKILL cannot be handled, so the master cannot pass it on: the manager is reparented to
 * init, keeps its workers, and they keep the listen socket. Whatever binds that port next fails, and
 * goes on failing - which is how swoole:server:watch used to take a container down with it.
 *
 * The existing start/stop tests do not cover this. They assert that the server stops answering, and an
 * orphaned tree does that too: it holds the port without serving anything. What has to be asserted is
 * that the processes are gone.
 *
 * Deliberately not through swoole:server:watch. Going through the supervisor means two server boots, a
 * file-watching loop and a restart, which is a minute of waiting to exercise a few lines. ProcessStopper
 * is what the supervisor calls, so it is what this points at.
 *
 * This covers the ordinary path against a real server. The path where the server has to be killed is in
 * ProcessStopperTest, against a process built to refuse to stop - reproducing that with swoole means
 * timing a stop against workers that happen to be busy, which is slow and not reliably reproducible.
 *
 * @see ProcessStopper
 */
final class SwooleServerStopLeavesNothingBehindTest extends ServerTestCase
{
    /**
     * A port of this test's own: it asserts on which processes exist, so a stray server answering on a
     * shared port would be reported as a leak of this one.
     */
    private const int PORT_OFFSET = 7;

    /**
     * A route that blocks its worker outright, for eight seconds.
     *
     * usleep() rather than the coroutine sleep behind /dummy-sleep: this environment runs with
     * coroutines disabled, where a coroutine sleep does not hold the worker at all - which left the
     * server idle and stopping instantly, and the test proving nothing.
     */
    private const string SLOW_ROUTE = '/test/blocking/8000';

    /**
     * Comfortably above it, so the server stops by itself and nothing has to be killed.
     */
    private const float PATIENT_TIMEOUT = 20.0;

    /**
     * Below swoole's own max_wait_time, so the master cannot have finished by the time it expires.
     */
    private const float IMPATIENT_TIMEOUT = 1.0;

    /**
     * More requests than the fixture has workers, so none of them is left idle.
     */
    private const int BUSY_REQUESTS = 4;

    /**
     * How long the requests are given to reach the workers before the server is stopped.
     *
     * Whole seconds, and an int: OpenSwoole\Coroutine::sleep() only takes an int, where Swoole's takes
     * a float. A float works on one engine and is a TypeError on the other.
     */
    private const int SECONDS_UNTIL_WORKERS_ARE_BUSY = 2;

    private ?Process $server = null;

    /**
     * What the stopper had to kill, written from inside the coroutine that does the stopping.
     *
     * A property rather than a variable inherited by reference, which the coding standard forbids -
     * and the closure needs $this anyway.
     *
     * @var list<int>
     */
    private array $killed = [];

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->deleteVarDirectory();
    }

    #[Override]
    protected function tearDown(): void
    {
        // Whatever the test did, nothing of the server may outlive it.
        if ($this->server instanceof Process) {
            (new ProcessStopper())->stop($this->server, 5.0);
            $this->server = null;
        }

        parent::tearDown();
    }

    /**
     * The ordinary case: given time, the server stops on its own and takes its tree with it.
     */
    public function testAServerGivenTimeToStopLeavesNothingRunning(): void
    {
        $tree = $this->startServer();

        $killed = $this->stopWhileBusy(self::PATIENT_TIMEOUT);

        self::assertSame([], $killed, 'The server had time to stop and should not have been killed.');
        self::assertSame([], $this->stillRunning($tree), $this->leakMessage($tree));
    }

    /**
     * The case the supervisor used to get wrong: the timeout expires while the server is still winding
     * down, so the master is killed. Everything below it has to go too.
     */
    public function testAServerKilledBeforeItFinishesLeavesNothingRunning(): void
    {
        $tree = $this->startServer();

        $killed = $this->stopWhileBusy(self::IMPATIENT_TIMEOUT);

        // Doubles as the check that the load reached the workers: an idle server stops well inside a
        // second and nothing would need killing.
        self::assertNotSame(
            [],
            $killed,
            'The server stopped inside a timeout it cannot meet, which means its workers were idle and '
            . 'this test exercised nothing.',
        );
        self::assertSame([], $this->stillRunning($tree), $this->leakMessage($tree));
    }

    /**
     * Starts the server and returns its whole tree, read while it is healthy.
     *
     * Read now rather than at stop time: once the master is killed its children are reparented, and
     * nothing connects them to it any more.
     *
     * @return list<int>
     */
    private function startServer(): array
    {
        $server = $this->createConsoleProcess([
            'swoole:server:run',
            '--host=localhost',
            sprintf('--port=%d', self::port(self::PORT_OFFSET)),
        ]);

        $server->setTimeout(120);
        $server->start();
        $this->server = $server;

        $this->runAsCoroutineAndWait(static function (): void {
            $client = HttpClient::fromDomain('localhost', self::port(self::PORT_OFFSET), false);

            self::assertTrue(
                $client->connect(self::connectTimeout(20), 1, true),
                'The server never came up.',
            );
        });

        $pid = $server->getPid();
        self::assertIsInt($pid);

        $tree = (new ProcessTree())->of($pid);

        self::assertGreaterThan(
            1,
            count($tree),
            'Only the master was found. Without a manager and workers below it there is nothing here '
            . 'that could be orphaned, and the test would prove nothing.',
        );

        return $tree;
    }

    /**
     * Stops the server while its workers are inside a long request, and reports what had to be killed.
     *
     * The stop happens in a coroutine beside the requests, not after them. Waiting for the requests
     * first was the mistake in the first version of this test: HttpClient::send() returns when the
     * response does, whatever timeout it was given, so the helper only came back once every request had
     * finished - and by then the workers were idle, the server stopped instantly, and the test proved
     * nothing.
     *
     * @return list<int> what the stopper had to kill
     */
    private function stopWhileBusy(float $timeout): array
    {
        $server = $this->server;
        self::assertInstanceOf(Process::class, $server);

        $this->killed = [];

        $this->runAsCoroutineAndWait(function () use ($server, $timeout): void {
            for ($request = 0; $request < self::BUSY_REQUESTS; $request++) {
                go(static function (): void {
                    try {
                        $client = HttpClient::fromDomain('localhost', self::port(self::PORT_OFFSET), false);

                        if ($client->connect(self::connectTimeout(), 1, true)) {
                            $client->send(self::SLOW_ROUTE);
                        }
                    } catch (Throwable) {
                        // Expected: the server is stopped from under these, which is the point.
                    }
                });
            }

            go(function () use ($server, $timeout): void {
                // Long enough for the requests to have reached the workers and started blocking.
                Coroutine::sleep(self::SECONDS_UNTIL_WORKERS_ARE_BUSY);

                $this->killed = (new ProcessStopper())->stop($server, $timeout);
            });
        });

        $this->server = null;

        return $this->killed;
    }

    /**
     * @param list<int> $tree
     * @return list<int>
     */
    private function stillRunning(array $tree): array
    {
        $processes = new ProcessTree();

        return array_values(array_filter($tree, $processes->isRunning(...)));
    }

    /**
     * @param list<int> $tree
     */
    private function leakMessage(array $tree): string
    {
        $processes = new ProcessTree();

        return 'Part of the server outlived the stop and still holds the listen socket: '
            . implode('; ', array_map($processes->describe(...), $this->stillRunning($tree)));
    }
}

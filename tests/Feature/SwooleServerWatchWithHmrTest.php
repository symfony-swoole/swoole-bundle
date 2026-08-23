<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Feature;

use Override;
use SwooleBundle\SwooleBundle\Client\HttpClient;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Test\ServerTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/**
 * HMR and swoole:server:watch running together, which is what a developer actually has.
 *
 * The other two tests each hold one half still. SwooleServerHMRRestartRequiredTest runs HMR with no
 * supervisor and checks that a change it cannot apply pauses it; SwooleServerWatchCacheClearTest runs
 * the supervisor with HMR off and checks that a restart clears the cache when the container was built
 * from what changed. Both are precise because only one thing is reacting. Neither says anything about
 * the pairing, and the pairing is the configuration that ships: the documented answer to a change a
 * reload cannot apply is "run it under swoole:server:watch", which is a claim about the two of them
 * together.
 *
 * ## What this asserts, and what it deliberately does not
 *
 * Only the outcome: after editing a file the container was built from, the cache directory is gone,
 * a new container has been compiled, and the server is answering again.
 *
 * Not the pause. The supervisor polls several times a second and HMR ticks every two, so the supervisor
 * almost always restarts the server before HMR gets a tick to notice anything - the pause is what
 * covers a developer running without a supervisor, and asserting on it here would be asserting on which
 * of two timers fired first. That is the kind of assertion that made the sibling test fail half of all
 * parallel runs, and it is not what this test is for.
 *
 * The restart also exercises something no other test covers: SIGTERM reaching a server whose workers
 * hold an HMR timer. A timer that is never cleared keeps a worker's reactor busy, so the worker cannot
 * exit until max_wait_time force-terminates it, and every restart here would pay that.
 *
 * @see \SwooleBundle\SwooleBundle\Server\WorkerHandler\HMRWorkerExitHandler
 * @see docs/hot-module-reload.md
 */
final class SwooleServerWatchWithHmrTest extends ServerTestCase
{
    private const string ENVIRONMENT = 'watch_hmr_pairing';

    private const string CONFIG_FILE = __DIR__ . '/../Fixtures/Symfony/app/config/watch_hmr_pairing/swoole.php';

    private const string MARKER = 'watch-with-hmr-marker.txt';

    /**
     * A port of this test's own, out of the worker's block. The suite leaks servers - a clean run peaks
     * at nineteen against the four it should have - and a stray one holding the shared offset would
     * answer for a server this test never started.
     */
    private const int PORT_OFFSET = 6;

    /**
     * How long the restart is given. Generous on purpose: it covers stopping a server, clearing the
     * cache, and a cold container compile, on a box running three other test workers.
     */
    private const int RESTART_TIMEOUT_SECONDS = 45;

    /**
     * How long the supervisor is given to take itself and its server down before it is stopped outright.
     */
    private const float STOP_TIMEOUT_SECONDS = 10.0;

    /**
     * Ahead of the container's own mtime by more than the second filemtime() resolves to.
     *
     * A plain touch sets the mtime to now, which can land in the very second the container was
     * compiled - and a resource is fresh while its mtime is less than or equal to the cache's. The
     * container then reads as fresh, the supervisor restarts without clearing anything, and the
     * restarted server reuses the container it was supposed to replace. That failed one run in three.
     */
    private const int STALE_BY_SECONDS = 5;

    private Filesystem $filesystem;

    /**
     * Held on the test rather than the method, because a supervisor left running outlives the test: it
     * keeps its server, its port and - fatally for a run under process isolation - the output pipe
     * PHPUnit is waiting to close.
     */
    private ?Process $watch = null;

    private int|false $configModifiedAt = false;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->filesystem = new Filesystem();
        $this->configModifiedAt = filemtime(self::CONFIG_FILE);
        self::assertIsInt($this->configModifiedAt);
        $this->deleteVarDirectory();
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->stopWatch();

        if ($this->configModifiedAt !== false) {
            touch(self::CONFIG_FILE, $this->configModifiedAt);
        }

        parent::tearDown();
    }

    public function testAConfigChangeIsAppliedByTheSupervisorWhileHmrIsRunningToo(): void
    {
        $this->startWatch();

        try {
            $this->awaitServer();

            $containerBefore = $this->compiledContainer();
            self::assertNotNull($containerBefore, 'The server started without compiling a container.');
            $compiledAt = filemtime($containerBefore);
            self::assertIsInt($compiledAt);

            $marker = $this->leaveMarkerInCache();

            $this->editConfigHarmlessly();

            $recompiled = $this->awaitContainerCompiledAfter($compiledAt);

            self::assertTrue(
                $recompiled,
                sprintf(
                    'No new container was compiled within %ds of editing a file the old one was built '
                    . 'from, so the edit never took effect - either the supervisor did not restart, or '
                    . 'the restarted server reused what was already in the cache.',
                    self::RESTART_TIMEOUT_SECONDS,
                ),
            );
            self::assertFileDoesNotExist(
                $marker,
                'The cache directory survived a restart the container was made stale for.',
            );

            $this->awaitServer();
        } finally {
            $this->stopWatch();
        }
    }

    private function startWatch(): void
    {
        $watch = $this->createConsoleProcess([
            'swoole:server:watch',
            '--console=console',
            sprintf('--path=config/%s', self::ENVIRONMENT),
            '--interval=200',
            '--',
            '--host=localhost',
            sprintf('--port=%d', self::port(self::PORT_OFFSET)),
        ], ['APP_ENV' => self::ENVIRONMENT]);

        $watch->setTimeout(180);
        $watch->start();

        $this->watch = $watch;
    }

    /**
     * SIGTERM rather than stop(): the supervisor handles it, and handling it is how the server it
     * spawned is stopped too. Killed outright, that server outlives the test and holds its port.
     *
     * Bounded, and then stopped the blunt way. An unbounded wait on a supervisor that is not going to
     * exit is a hung test rather than a failing one, and a hung test under process isolation takes the
     * whole run with it.
     */
    private function stopWatch(): void
    {
        $watch = $this->watch;
        $this->watch = null;

        if (!$watch instanceof Process || !$watch->isRunning()) {
            return;
        }

        $watch->signal(SIGTERM);

        $deadline = microtime(true) + self::STOP_TIMEOUT_SECONDS;

        while ($watch->isRunning() && microtime(true) < $deadline) {
            usleep(100_000);
        }

        $watch->stop(1.0);
    }

    /**
     * Saves the environment's own config, without changing a byte of it.
     *
     * A touch does everything the test needs: the supervisor's watcher compares mtimes, and so does the
     * container's freshness, so this is a change to both of them - and the container that gets rebuilt
     * comes out identical, which is the point. The rebuild is what is being observed.
     *
     * Content is deliberately left alone because this file is synchronised into the container by
     * mutagen. Writing it inside the container propagates the new content to the host, and restoring
     * it afterwards is then a conflicting change that mutagen resolves in the host's favour - putting
     * the edit back. An mtime-only change is invisible to a content-based sync, so nothing races.
     */
    private function editConfigHarmlessly(): void
    {
        touch(self::CONFIG_FILE, time() + self::STALE_BY_SECONDS);
    }

    private function awaitContainerCompiledAfter(int $compiledAt): bool
    {
        $deadline = microtime(true) + self::RESTART_TIMEOUT_SECONDS;

        while (microtime(true) < $deadline) {
            clearstatcache();
            $container = $this->compiledContainer();

            if ($container !== null && (int) filemtime($container) > $compiledAt) {
                return true;
            }

            usleep(500_000);
        }

        return false;
    }

    private function compiledContainer(): ?string
    {
        $matches = glob(sprintf('%s/cache/%s/*Container.php', $this->getVarDirectoryPath(), self::ENVIRONMENT));

        return $matches === false || $matches === [] ? null : $matches[0];
    }

    private function leaveMarkerInCache(): string
    {
        $marker = sprintf('%s/cache/%s/%s', $this->getVarDirectoryPath(), self::ENVIRONMENT, self::MARKER);
        $this->filesystem->dumpFile($marker, 'written while the first server was up');

        return $marker;
    }

    private function awaitServer(): void
    {
        $this->runAsCoroutineAndWait(static function (): void {
            $client = HttpClient::fromDomain('localhost', self::port(self::PORT_OFFSET), false);

            self::assertTrue(
                $client->connect(self::connectTimeout(20), 1, true),
                'The supervisor never brought a server up on the port.',
            );
        });
    }
}

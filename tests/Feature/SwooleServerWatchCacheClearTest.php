<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Feature;

use Override;
use Swoole\Coroutine;
use SwooleBundle\SwooleBundle\Client\HttpClient;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Test\ServerTestCase;
use SwooleBundle\SwooleBundle\Tests\Helper\TestToken;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/**
 * What swoole:server:watch does to the cache directory when it restarts the server.
 *
 * A restart is what applies the changes a worker reload cannot, and it applies them by being a new
 * process: new master, kernel booted afresh, workers forked from that. What a new process does not
 * necessarily do is build a new container - a debug kernel checks its container's freshness on boot and
 * would, but nothing checks the service pools and warmed caches beside it, and with debug off nothing
 * checks the container either, because a ConfigCache without debug calls any file that exists fresh.
 * So the supervisor clears the directory itself, and only when the change was one the container was
 * built from: compiling a container again is not free, and most restarts are a changed class body that
 * the container recorded nothing about.
 *
 * Both cases restart for the same reason - a file in the watched path changed - and differ only in
 * whether the container is stale. The control is what keeps the assertion from passing on a restart
 * that would have cleared the cache whatever the container looked like.
 *
 * A marker file stands in for everything in that directory. Asserting on the container alone would
 * prove nothing: a debug kernel rebuilds a stale container by itself, so it would come back either way.
 * The marker is something only the supervisor's clearing removes.
 *
 * @see \SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\Command\ServerWatchCommand
 * @see docs/hot-module-reload.md
 */
final class SwooleServerWatchCacheClearTest extends ServerTestCase
{
    private const string ENVIRONMENT = 'watch_cache_clear';

    /**
     * The environment's own config, which its container is built from - so touching it is exactly the
     * change that has to cost a cache clear.
     */
    private const string CONFIG_FILE = __DIR__ . '/../Fixtures/Symfony/app/config/watch_cache_clear/swoole.php';

    private const string MARKER = 'watch-cache-clear-marker.txt';

    /**
     * Past the container's own mtime by more than the second filemtime() resolves to - a resource is
     * fresh while its mtime is less than or equal to the cache's.
     */
    private const int STALE_BY_SECONDS = 5;

    /**
     * A port of this test's own, for the same reason the HMR restart test has one - and one more
     * besides: the supervisor restarts a server that will not start, up to thirty times. Pointed at a
     * port some other test's leaked server is holding, that turns a clean failure into a burst of
     * processes on a suite that is already leaking them.
     */
    private const int PORT_OFFSET = 5;

    private Filesystem $filesystem;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->filesystem = new Filesystem();
        $this->deleteVarDirectory();
        $this->filesystem->mkdir($this->watchedDirectory());
        $this->filesystem->dumpFile($this->triggerFile(), "<?php\n// nothing has changed yet\n");
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->filesystem->remove($this->watchedDirectory());

        parent::tearDown();
    }

    public function testAStaleContainerHasTheCacheClearedBeforeTheServerIsRestarted(): void
    {
        $configWasModifiedAt = filemtime(self::CONFIG_FILE);
        self::assertIsInt($configWasModifiedAt);

        $watch = $this->startWatch();

        try {
            $this->awaitServer();
            $marker = $this->leaveMarkerInCache();

            touch(self::CONFIG_FILE, time() + self::STALE_BY_SECONDS);
            $this->triggerRestart();
            $this->awaitServer();

            self::assertFileDoesNotExist(
                $marker,
                'The container was stale and the cache directory survived the restart, so the restarted '
                . 'server is reading what the change was supposed to replace.',
            );
        } finally {
            touch(self::CONFIG_FILE, $configWasModifiedAt);
            $this->stopWatch($watch);
        }

        $output = $this->outputOf($watch);

        self::assertRestartedOnce($output);
        self::assertStringContainsString('container is stale', $output);
    }

    /**
     * The control: the same restart, with a container that still matches its sources, keeps the cache.
     *
     * Without it the test above would also pass on a supervisor that cleared the directory on every
     * restart - correct here, and a full container compile on every saved file.
     */
    public function testAFreshContainerKeepsItsCacheAcrossTheRestart(): void
    {
        $watch = $this->startWatch();

        try {
            $this->awaitServer();
            $marker = $this->leaveMarkerInCache();

            $this->triggerRestart();
            $this->awaitServer();

            self::assertFileExists(
                $marker,
                'Nothing the container was built from changed, so clearing the cache only bought the '
                . 'next boot a container compile it did not need.',
            );
        } finally {
            $this->stopWatch($watch);
        }

        $output = $this->outputOf($watch);

        // Asserted here above all: without it this test also passes on a supervisor that never
        // restarted at all, since a cache nobody touched keeps its marker either way.
        self::assertRestartedOnce($output);
        self::assertStringNotContainsString('container is stale', $output);
    }

    /**
     * The supervisor is a console command like any other, so the fixture's console runs it - and
     * --console is what lets it start the server with that same console rather than the bin/console a
     * real application has.
     */
    private function startWatch(): Process
    {
        $watch = $this->createConsoleProcess([
            'swoole:server:watch',
            '--console=console',
            sprintf('--path=%s', $this->watchedPathRelativeToProjectDir()),
            '--interval=200',
            '--',
            '--host=localhost',
            sprintf('--port=%d', self::port(self::PORT_OFFSET)),
        ], ['APP_ENV' => self::ENVIRONMENT]);

        $watch->setTimeout(120);
        $watch->start();

        return $watch;
    }

    private function stopWatch(Process $watch): void
    {
        // SIGTERM rather than stop(): the supervisor handles it, and handling it is how the server it
        // spawned is stopped too. Killed outright, that server would outlive the test and hold its port.
        $watch->signal(SIGTERM);
        $watch->waitUntil(static fn(): bool => false);
    }

    /**
     * A restart is triggered by a change in the watched path, which is deliberately not the config file
     * the container is built from - the two have to be separable for the control to mean anything.
     */
    private function triggerRestart(): void
    {
        $this->filesystem->dumpFile($this->triggerFile(), sprintf("<?php\n// %s\n", uniqid('change', true)));
    }

    private function leaveMarkerInCache(): string
    {
        $marker = sprintf('%s/cache/%s/%s', $this->getVarDirectoryPath(), self::ENVIRONMENT, self::MARKER);
        $this->filesystem->dumpFile($marker, 'written while the server was up');

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

            // The restart settles a second after the old process goes, and the marker must not be
            // written into a directory the supervisor is about to remove.
            Coroutine::sleep(1);
        });
    }

    private static function assertRestartedOnce(string $output): void
    {
        self::assertSame(
            1,
            substr_count($output, 'restarting server'),
            'The change in the watched path had to restart the server exactly once.',
        );
    }

    private function outputOf(Process $watch): string
    {
        return $watch->getOutput() . $watch->getErrorOutput();
    }

    /**
     * Watched from the worker's own var directory, so that parallel workers never see each other's
     * changes and restart for them.
     */
    private function watchedDirectory(): string
    {
        return $this->getVarDirectoryPath() . '/watched';
    }

    private function watchedPathRelativeToProjectDir(): string
    {
        return sprintf('var%s/watched', TestToken::suffix());
    }

    private function triggerFile(): string
    {
        return $this->watchedDirectory() . '/Trigger.php';
    }
}

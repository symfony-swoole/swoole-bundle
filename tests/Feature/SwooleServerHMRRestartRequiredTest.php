<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Feature;

use Override;
use Swoole\Coroutine;
use SwooleBundle\SwooleBundle\Client\HttpClient;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Test\ReplacedContentController;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Test\ServerTestCase;
use Symfony\Component\Process\Process;

/**
 * What HMR does with a change it cannot apply.
 *
 * Reloading re-forks the workers from the master's memory image, which reaches a class no worker had
 * loaded yet and nothing else. A change to the compiled container falls outside that: the kernel boots
 * in the master before Server::start(), so a reloaded worker never boots one and never compiles
 * anything - emptying the cache directory under a running server changes nothing at all, because no
 * process re-reads it. The old behaviour was to reload anyway, dropping every connection the workers
 * held and leaving the old container in place, with nothing said about why the change had not landed.
 *
 * The unit tests around ContainerFreshness and RestartAwareHotModuleReloader pin the decision down.
 * What only a running server can show is that the decision is reached at all from inside a worker,
 * which is the whole difficulty: whether a container is fresh is a question Symfony answers once during
 * boot and memoizes for the process, and stat() results are cached per process too. Neither shows up
 * until something asks a second time, hours in, from a process that was forked rather than booted.
 *
 * Both tests make the same edit. The difference between them is the stale container, so the control
 * below is what keeps the assertion above from passing because nothing was ever going to reload.
 *
 * @see docs/hot-module-reload.md
 */
final class SwooleServerHMRRestartRequiredTest extends ServerTestCase
{
    use ReplacedContentController;

    private const string ENVIRONMENT = 'hmr_restart';

    /**
     * The environment's own config file, which the compiled container is built from - so touching it is
     * exactly the change a reload cannot apply.
     */
    private const string CONFIG_FILE = __DIR__ . '/../Fixtures/Symfony/app/config/hmr_restart/swoole.php';

    /**
     * What the controller answers before either test rewrites it.
     */
    private const string ORIGINAL_RESPONSE = 'Wrong response!';

    private const string PAUSE_MESSAGE = 'Hot module reload is paused because';

    /**
     * A port of this test's own, out of the worker's block, rather than the offset every other test
     * reaches for.
     *
     * The suite leaks servers - a clean run peaks at nineteen of them against the four it should have -
     * and each one keeps its own HMR running against the same generated controller. Sharing offset 0
     * means one of those strays can answer this test's request and serve a reload this test is asserting
     * did not happen, which is a failure in something else's cleanup reported against this feature.
     */
    private const int PORT_OFFSET = 4;

    /**
     * Ahead of the container's own mtime by more than the second filemtime() resolves to.
     *
     * Without it a touch landing in the same second the container was compiled reads as no change at
     * all: a resource is fresh while its mtime is less than or equal to the cache's.
     */
    private const int STALE_BY_SECONDS = 5;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->deleteVarDirectory();
        $this->writeOriginalTestController();
    }

    public function testAStaleContainerPausesHotModuleReloadInsteadOfReloadingAroundIt(): void
    {
        $serverRun = $this->startServer();
        $configWasModifiedAt = filemtime(self::CONFIG_FILE);
        self::assertIsInt($configWasModifiedAt);

        try {
            $this->runAsCoroutineAndWait(function (): void {
                $this->deferRestoreOriginalTemplateControllerResponse();

                $client = $this->connect();
                $this->assertResponseIs(self::ORIGINAL_RESPONSE, $client);
                $this->awaitControllerBeingWatched();

                touch(self::CONFIG_FILE, time() + self::STALE_BY_SECONDS);
                $this->awaitPauseTakingEffect();

                // Only now, with reloading paused, is the worker that will answer the last request the
                // one that answers this one. Until the pause is in effect a reload can still land - and
                // one does, triggered by setUp's own write of this controller, which reaches the watch
                // set a tick after the server starts. A re-forked worker has never loaded the controller
                // class, so it reads whatever is on disk the first time it is asked, and the rewrite
                // below would be served without any reload being involved at all. That is what made this
                // test fail about half of all parallel runs: not a reload, but a first load.
                $this->assertResponseIs(self::ORIGINAL_RESPONSE, $client);

                $this->replaceContentInTestController('Hello world from a worker that never reloaded!');

                Coroutine::sleep(self::severalTicks());

                $this->assertResponseIs(
                    self::ORIGINAL_RESPONSE,
                    $client,
                    'The workers were reloaded while the container was stale. That dropped every '
                    . 'connection they held and still left the old container in place, which is what '
                    . 'the pause exists to avoid.',
                );
            });
        } finally {
            touch(self::CONFIG_FILE, $configWasModifiedAt);
            $serverRun->stop();
        }

        $log = $this->serverLog();

        self::assertStringContainsString(
            self::PAUSE_MESSAGE,
            $log,
            'HMR stopped reloading but never said why, which leaves a developer watching a saved file '
            . 'do nothing, with nothing to read about it.',
        );
        self::assertStringContainsString(
            'the compiled container no longer matches the files it was built from',
            $log,
            'The pause was reported for some other reason than the container that was made stale.',
        );
        self::assertSame(
            1,
            substr_count($log, self::PAUSE_MESSAGE),
            'The pause was reported more than once. Nothing gets better until the server is restarted, '
            . 'so a line per tick is a line every couple of seconds for as long as it takes somebody '
            . 'to read the first one.',
        );
    }

    /**
     * The control: the same edit, with nothing standing in the way, is applied as it always was.
     *
     * Without this the test above proves only that the response did not change, which is also what a
     * server whose HMR never worked would show.
     */
    public function testTheSameChangeIsAppliedWhileTheContainerStillMatchesItsSources(): void
    {
        $serverRun = $this->startServer();
        $expected = 'Hello world from swoole reloaded worker by HMR!';

        try {
            $this->runAsCoroutineAndWait(function () use ($expected): void {
                $this->deferRestoreOriginalTemplateControllerResponse();

                $client = $this->connect();
                $this->assertResponseIs(self::ORIGINAL_RESPONSE, $client);
                $this->awaitControllerBeingWatched();

                $this->replaceContentInTestController($expected);

                Coroutine::sleep(self::severalTicks());

                $this->assertResponseIs($expected, $client, 'The workers were never reloaded.');
            });
        } finally {
            $serverRun->stop();
        }

        // Deliberately no assertion that HMR never paused here, although that was the first thing this
        // control tried to say. The container tracks the controller directory through a GlobResource,
        // so rewriting the controller - the very edit that proves the reload works - also makes the
        // container stale, and a pause on a later tick is then correct rather than a defect. Which of
        // the two ticks comes first depends on timing, so the assertion held locally and failed under
        // coverage on CI.
        //
        // The response assertion above already carries this test's meaning: the change was applied, so
        // HMR was not paused at the moment that mattered.
    }

    private function startServer(): Process
    {
        $serverRun = $this->createConsoleProcess([
            'swoole:server:run',
            '--host=localhost',
            sprintf('--port=%d', self::port(self::PORT_OFFSET)),
        ], ['APP_ENV' => self::ENVIRONMENT]);

        $serverRun->setTimeout(60);
        $serverRun->start();

        return $serverRun;
    }

    private function connect(): HttpClient
    {
        $client = HttpClient::fromDomain('localhost', self::port(self::PORT_OFFSET), false);
        self::assertTrue($client->connect(self::connectTimeout(), waitIfNoConnection: true));

        return $client;
    }

    /**
     * Waits out a tick before the controller is rewritten, and the tests do not work without it.
     *
     * StatHMR watches what the worker has included, which it learns at the end of each tick - so the
     * controller only joins the watch set on the first tick after a request has reached it. Rewritten
     * before that tick, the file is first seen with its new mtime already in place, and no later tick
     * has anything to compare against: the edit would be missed whether or not anything was paused,
     * and both tests would report on the wrong thing.
     */
    private function awaitControllerBeingWatched(): void
    {
        Coroutine::sleep(self::coverageEnabled() ? 6 : 4);
    }

    /**
     * Long enough for a tick to have run since the config was made stale, so the pause is in force
     * before anything else is changed.
     */
    private function awaitPauseTakingEffect(): void
    {
        Coroutine::sleep(self::coverageEnabled() ? 5 : 3);
    }

    private function assertResponseIs(string $expected, HttpClient $client, string $message = ''): void
    {
        $response = $client->send($this->replacedContentRoute())['response'];

        self::assertSame(200, $response['statusCode'], $message);
        self::assertSame($expected, $response['body'], $message);
    }

    /**
     * Several HMR ticks - the timer runs every two seconds - so a reload has every chance to happen
     * before either test decides it did not.
     */
    private static function severalTicks(): int
    {
        return self::coverageEnabled() ? 10 : 7;
    }

    private function serverLog(): string
    {
        $path = sprintf('%s/log/%s.log', $this->getVarDirectoryPath(), self::ENVIRONMENT);

        if (!is_file($path)) {
            // Nothing worth logging happened, which is the whole point in the control's case.
            return '';
        }

        return (string) file_get_contents($path);
    }
}

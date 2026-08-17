<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Feature;

use donatj\MockWebServer\MockWebServer;
use donatj\MockWebServer\Response as MockResponse;
use Override;
use SwooleBundle\SwooleBundle\Client\HttpClient;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Controller\TracedHttpClientController;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Test\ServerTestCase;

/**
 * Guards that concurrent requests do not end up sharing one profiler http panel.
 *
 * With the profiler on, every outbound call is appended to an ArrayObject on the traced client and
 * HttpClientDataCollector::lateCollect() empties it again on kernel.response. One traced client
 * shared by the whole worker therefore holds whatever every request in flight has sent so far, and
 * each request's panel shows the lot - until whichever finishes first empties it and the rest show
 * nothing.
 *
 * Symfony marks the traced client resettable and it would have been pooled on that alone, except that
 * DecoratorServicePass hands the tags of a decoration chain to whichever decorator ends up outermost,
 * leaving the traced client in the middle of the chain with none. HttpClientProcessor pools it by
 * class instead.
 *
 * Deliberately not a fiber viber test. fiber viber turns this into a hard ConcurrencyException on the
 * response buffers the dropped traces hold, which is louder, but the sharing is a defect on its own:
 * the panel is wrong whether or not anything is watching ownership. Asserting the trace counts states
 * that directly and runs in the profiler environment every other collector test uses.
 *
 * The calls go through a real server rather than a mocked client, because a trace only exists for a
 * call something actually made.
 *
 * @see \SwooleBundle\SwooleBundle\Bridge\Symfony\HttpClient\HttpClientProcessor
 */
final class HttpClientCoroutineSafetyTest extends ServerTestCase
{
    private const string ENV = 'coroutines_profiler';

    private const string PATH = '/traced-http-client';

    private const string MOCK_BODY = 'traced-by-the-profiler';

    /**
     * Enough in flight at once that a shared client would plainly be holding more than one trace.
     */
    private const int CONCURRENT_REQUESTS = 4;

    private const int CLIENT_TIMEOUT_SECONDS = 30;

    private const int PROCESS_TIMEOUT_SECONDS = 60;

    /**
     * PHP's built-in server, which the mock web server runs on, handles one connection at a time
     * unless it is told to fork. Whether that matters depends on the engine: open/swoole does not hook
     * curl, so the outbound calls queue inside the worker and only ever reach the mock server one at a
     * time - but an engine that does hook it sends all of them at once, and the ones that find no
     * listener free come back as a transport error and a 500 out of a request that did nothing wrong.
     * Forking removes the difference.
     */
    private const string SERVER_WORKERS_ENV = 'PHP_CLI_SERVER_WORKERS';

    private ?MockWebServer $mockWebServer = null;

    private ?string $previousServerWorkers = null;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        if (!extension_loaded('sockets')) {
            self::markTestSkipped('Extension sockets is not loaded, which donatj/mock-webserver needs.');
        }

        $this->deleteVarDirectory();
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->mockWebServer?->stop();
        $this->mockWebServer = null;
        $this->restoreServerWorkers();

        parent::tearDown();
    }

    public function testEachRequestOnlySeesItsOwnOutboundCall(): void
    {
        $url = $this->startMockWebServer();

        /** @var list<array{statusCode: int, report: mixed}> $responses */
        $responses = [];

        // Assertions run after the server scenario: an assertion failure thrown from inside a
        // coroutine escapes the coroutine pool and takes the whole PHP process down with it, which
        // PHPUnit can then only report as "test ended unexpectedly".
        // phpcs:ignore SlevomatCodingStandard.PHP.DisallowReference
        $this->withServer($url, function () use (&$responses): void {
            $waitGroup = $this->getSwoole()->waitGroup();

            for ($i = 0; $i < self::CONCURRENT_REQUESTS; ++$i) {
                // phpcs:ignore SlevomatCodingStandard.PHP.DisallowReference
                go(static function () use ($waitGroup, $i, &$responses): void {
                    $waitGroup->add();

                    $client = HttpClient::fromDomain('localhost', self::port(), false);

                    if ($client->connect(self::connectTimeout(), waitIfNoConnection: true)) {
                        $response = $client->send(
                            sprintf('%s/%d', self::PATH, $i),
                            timeout: self::CLIENT_TIMEOUT_SECONDS,
                        )['response'];
                        $responses[] = [
                            'statusCode' => $response['statusCode'],
                            'report' => $response['headers'][mb_strtolower(TracedHttpClientController::REPORT_HEADER)]
                                ?? 'no report header',
                        ];
                    }

                    $waitGroup->done();
                });
            }

            $waitGroup->wait(self::PROCESS_TIMEOUT_SECONDS);
        });

        self::assertCount(self::CONCURRENT_REQUESTS, $responses, 'Every request must have been answered.');

        foreach ($responses as $response) {
            self::assertSame(
                200,
                $response['statusCode'],
                'The server failed to answer a request that should have been routine. It logged: '
                    . $this->serverLog(),
            );
            self::assertIsString($response['report']);
            self::assertStringContainsString(
                'traces=1 own=1',
                $response['report'],
                'A request must see exactly one traced call, its own. Seeing more is one traced client '
                    . 'shared by every coroutine, which puts other requests\' calls in this request\'s '
                    . 'profiler panel; seeing none is another request\'s collect having emptied it first. '
                    . "Got: {$response['report']}",
            );
        }
    }

    private function startMockWebServer(): string
    {
        // MockWebServer spawns its server as a child process, so it inherits this - and it has to be
        // set before start(), since that is when the child is forked.
        $this->previousServerWorkers = getenv(self::SERVER_WORKERS_ENV) === false
            ? null
            : (string) getenv(self::SERVER_WORKERS_ENV);
        putenv(sprintf('%s=%d', self::SERVER_WORKERS_ENV, self::CONCURRENT_REQUESTS));

        $this->mockWebServer = new MockWebServer();
        $this->mockWebServer->start();

        return $this->mockWebServer->setResponseOfPath('/probe', new MockResponse(self::MOCK_BODY));
    }

    private function restoreServerWorkers(): void
    {
        if ($this->previousServerWorkers === null) {
            putenv(self::SERVER_WORKERS_ENV);

            return;
        }

        putenv(sprintf('%s=%s', self::SERVER_WORKERS_ENV, $this->previousServerWorkers));
        $this->previousServerWorkers = null;
    }

    /**
     * What the server logged, so that a status nobody expected explains itself rather than leaving the
     * next reader with a bare "500 is not 200" from a machine they cannot reach.
     */
    private function serverLog(): string
    {
        $log = sprintf('%s/log/%s.log', $this->getVarDirectoryPath(), self::ENV);

        if (!is_file($log)) {
            return 'no server log at ' . $log;
        }

        $contents = file_get_contents($log);

        if ($contents === false || $contents === '') {
            return 'server log is empty';
        }

        $lines = array_filter(
            explode("\n", $contents),
            static fn(string $line): bool => str_contains($line, 'CRITICAL') || str_contains($line, 'ERROR'),
        );

        return $lines === [] ? 'nothing critical in the server log' : implode("\n", array_slice($lines, -3));
    }

    /**
     * @param callable(): void $scenario
     */
    private function withServer(string $mockWebServerUrl, callable $scenario): void
    {
        $envs = ['APP_ENV' => self::ENV, 'WORKER_COUNT' => '1', 'MOCK_WEBSERVER_URL' => $mockWebServerUrl];

        // setUp() throws the compiled cache away before the test, so compile the container here instead
        // of leaving it for the server to do while booting
        $clearCache = $this->createConsoleProcess(['cache:clear'], $envs);
        $clearCache->setTimeout(self::PROCESS_TIMEOUT_SECONDS);
        $clearCache->disableOutput();
        $clearCache->run();

        $this->assertProcessSucceeded($clearCache);

        // swoole:server:start returns only once the server is actually listening, unlike
        // swoole:server:run which stays in the foreground and leaves the client racing the boot.
        $serverStart = $this->createConsoleProcess([
            'swoole:server:start',
            '--host=localhost',
            sprintf('--port=%d', self::port()),
        ], $envs);

        $serverStart->setTimeout(self::PROCESS_TIMEOUT_SECONDS);
        $serverStart->run();

        $this->assertProcessSucceeded($serverStart);

        $connected = false;

        // phpcs:ignore SlevomatCodingStandard.PHP.DisallowReference
        $this->runAsCoroutineAndWait(function () use ($scenario, $envs, &$connected): void {
            $this->deferServerStop([], $envs);

            $client = HttpClient::fromDomain('localhost', self::port(), false);
            $connected = $client->connect(self::connectTimeout(), 1, true);

            if (!$connected) {
                return;
            }

            $scenario();
        });

        self::assertTrue($connected, 'the server was started but never answered.');
    }
}

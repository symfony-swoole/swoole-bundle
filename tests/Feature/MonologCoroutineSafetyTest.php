<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Feature;

use Co;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use SwooleBundle\SwooleBundle\Client\HttpClient;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Test\ServerTestCase;

/**
 * Concurrent logging through the monolog stream handler, which the suite never exercised.
 *
 * The fixture app logs to a file handler at level `warning`, and nothing any other test does reaches that
 * level - so the handler's stream was never opened, and everything done to a closed stream is a no-op.
 * That hid a whole class of failure: the handler is pooled per coroutine and reset on release, the reset
 * closes the file (Monolog does that on purpose, so externally rotated files are picked up), and the file
 * was opened by an entirely different coroutine - the one {@see SerialQueue} spawned to do the write.
 *
 * Two requests are not enough to see it. The pooled handlers have to be filled, released, and used again,
 * so the assertions below are made after two waves: the first opens the streams, the release between them
 * closes them, the second writes to whatever the reset left behind.
 *
 * What is asserted is the record count. A dropped record is the quiet half of the same bug - writes that
 * fail inside the queue consumer are caught there and reported to stderr, so the request still answers 200
 * and only the log is short.
 *
 * @see \SwooleBundle\SwooleBundle\Bridge\Monolog\CoroutineSafeWrites
 */
final class MonologCoroutineSafetyTest extends ServerTestCase
{
    /**
     * Above the environments' `max_service_instances`, so handlers are recycled rather than one per request.
     */
    private const int REQUESTS_PER_WAVE = 25;

    /**
     * Two waves: the first opens the handlers' streams, the release between them resets - and so closes -
     * those streams, the second writes to whatever the reset left behind.
     */
    private const int WAVES = 2;

    private const string FINAL_MARKER = 'after-the-load';

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->deleteVarDirectory();
    }

    /**
     * @param array{APP_ENV: string, APP_DEBUG: string, WORKER_COUNT: string, REACTOR_COUNT: string} $envs
     */
    #[DataProvider('loggingEnvironmentDataProvider')]
    public function testConcurrentLoggingKeepsEveryRecordAndTheWorkerAlive(array $envs): void
    {
        $this->startServer($envs);

        $markersByWave = self::markersByWave();
        $markers = [...array_merge(...$markersByWave), self::FINAL_MARKER];

        $this->runAsCoroutineAndWait(function () use ($envs, $markersByWave): void {
            $this->deferServerStop([], $envs);

            self::assertTrue($this->awaitHealthEndpoint(self::port(1)));

            foreach ($markersByWave as $waveMarkers) {
                $waitGroup = $this->getSwoole()->waitGroup();

                foreach ($waveMarkers as $marker) {
                    go(static function () use ($waitGroup, $marker): void {
                        $waitGroup->add();

                        $client = HttpClient::fromDomain('localhost', self::port(1), false);
                        self::assertTrue($client->connect(self::connectTimeout(), waitIfNoConnection: true));

                        $response = $client->send(sprintf('/log-warning/%s', $marker))['response'];

                        self::assertSame(200, $response['statusCode'], sprintf('Request %s failed.', $marker));

                        $waitGroup->done();
                    });
                }

                $waitGroup->wait(20);
            }

            // the worker dying takes the whole thing with it, so whether it is still there has to be asked
            // after the load, not during it
            $client = HttpClient::fromDomain('localhost', self::port(1), false);
            self::assertTrue(
                $client->connect(self::connectTimeout(), 1, true),
                'The worker did not survive concurrent logging.',
            );
            self::assertSame(
                200,
                $client->send(sprintf('/log-warning/%s', self::FINAL_MARKER))['response']['statusCode'],
            );

            // the last records are handed to the queue, not written by the request itself
            Co::sleep(1);
        });

        $log = $this->readApplicationLog($envs['APP_ENV']);

        foreach ($markers as $marker) {
            self::assertStringContainsString(
                sprintf('Coroutine logging test: %s', $marker),
                $log,
                sprintf('Record %s never made it into the log.', $marker),
            );
        }

        $serverLog = $this->readServerLog($envs['APP_ENV']);

        self::assertStringNotContainsString('Serially queued work failed', $serverLog);
        self::assertStringNotContainsString('abnormal exit', $serverLog);
    }

    /**
     * @return iterable<string, array{array{
     *     APP_ENV: string,
     *     APP_DEBUG: string,
     *     WORKER_COUNT: string,
     *     REACTOR_COUNT: string,
     * }}>
     */
    public static function loggingEnvironmentDataProvider(): iterable
    {
        foreach (['coroutines', 'coroutines_fiber_viber'] as $env) {
            yield $env => [[
                'APP_ENV' => $env,
                'APP_DEBUG' => '1',
                'WORKER_COUNT' => '1',
                'REACTOR_COUNT' => '1',
            ]];
        }
    }

    /**
     * @return array<int, array<int, string>>
     */
    private static function markersByWave(): array
    {
        $perWave = self::coverageEnabled() ? 8 : self::REQUESTS_PER_WAVE;
        $markersByWave = [];

        for ($wave = 1; $wave <= self::WAVES; ++$wave) {
            $waveMarkers = [];

            for ($request = 0; $request < $perWave; ++$request) {
                $waveMarkers[] = sprintf('wave%d-request%d', $wave, $request);
            }

            $markersByWave[] = $waveMarkers;
        }

        return $markersByWave;
    }

    /**
     * @param array<string, string> $envs
     */
    private function startServer(array $envs): void
    {
        $clearCache = $this->createConsoleProcess(['cache:clear'], $envs);
        $clearCache->setTimeout(30);
        $clearCache->disableOutput();
        $clearCache->run();

        $this->assertProcessSucceeded($clearCache);

        $serverStart = $this->createConsoleProcess([
            'swoole:server:start',
            '--host=localhost',
            sprintf('--port=%d', self::port(1)),
            // The environments under test have the api server on, and it binds a port of its own.
            sprintf('--api-port=%d', self::port(3)),
        ], $envs);
        $serverStart->setTimeout(30);
        $serverStart->disableOutput();
        $serverStart->run();

        $this->assertProcessSucceeded($serverStart);
    }

    private function readApplicationLog(string $env): string
    {
        $path = sprintf('%s/log/%s.log', $this->getVarDirectoryPath(), $env);

        self::assertFileExists($path, 'Nothing was logged at all, the test would prove nothing.');

        return (string) file_get_contents($path);
    }

    private function readServerLog(string $env): string
    {
        $path = sprintf('%s/log/swoole_%s.log', $this->getVarDirectoryPath(), $env);

        return file_exists($path) ? (string) file_get_contents($path) : '';
    }
}

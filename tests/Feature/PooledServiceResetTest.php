<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Feature;

use Override;
use SwooleBundle\SwooleBundle\Client\HttpClient;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Test\ServerTestCase;

/**
 * Guards the reset cycle of pooled services.
 *
 * A pooled service instance is recycled across coroutines, so anything it accumulated while serving one
 * request is still there when the next request gets that same instance - unless it is reset in between.
 * That reset is driven by the resetter attached to its ServicePoolEntry, and StatefulServicesPass takes
 * those resetters from Symfony's `services_resetter`, which lists exactly the services tagged
 * `kernel.reset`.
 *
 * Autoconfiguration adds `kernel.reset` to every ResetInterface implementation, but bundles registering
 * their own services do not autoconfigure them - FrameworkBundle's data collectors, MonologBundle's
 * loggers, DoctrineBundle's and SecurityBundle's debug/traceable decorators all lack the tag. Those
 * services were still pooled (a data collector is pooled for being a data collector), just with a null
 * resetter, so they accumulated forever inside the worker until a data collector was cloned by the
 * profiler's VarCloner at kernel.terminate and exhausted the memory limit.
 *
 * Every test hits its route repeatedly against the same single-worker server and asserts that the
 * instance never carries state over from an earlier request.
 */
final class PooledServiceResetTest extends ServerTestCase
{
    private const string ENV = 'coroutines_profiler';

    private const int REQUESTS = 5;

    private const int RELEASE_GRACE_MICROSECONDS = 300_000;

    /**
     * Generous on purpose: both commands are awaited synchronously and return as soon as they are done,
     * so this only ever caps a machine that has genuinely stalled.
     */
    private const int PROCESS_TIMEOUT_SECONDS = 60;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->deleteVarDirectory();
    }

    /**
     * The regression itself: registered without autoconfiguration, so it implements ResetInterface but
     * carries no `kernel.reset` tag - exactly how FrameworkBundle & co. register their own services.
     */
    public function testStatefulServiceWithoutKernelResetTagIsResetBetweenRequests(): void
    {
        $this->assertNothingSurvivesBetweenRequests('/leaky-resource/stateful-only');
    }

    /**
     * Same regression through the other route into the pool: a data collector is pooled for being a
     * data collector, and FrameworkBundle's collectors are likewise registered without autoconfiguration.
     */
    public function testDataCollectorWithoutKernelResetTagIsResetBetweenRequests(): void
    {
        $this->assertNothingSurvivesBetweenRequests('/leaky-collector/plain');
    }

    public function testKernelResetTaggedServiceIsResetBetweenRequests(): void
    {
        $this->assertNothingSurvivesBetweenRequests('/leaky-resource/kernel-reset');
    }

    public function testServiceWithResetOnEachRequestIsResetBetweenRequests(): void
    {
        $this->assertNothingSurvivesBetweenRequests('/leaky-resource/reset-on-each-request');
    }

    public function testDataCollectorWithResetOnEachRequestIsResetBetweenRequests(): void
    {
        $this->assertNothingSurvivesBetweenRequests('/leaky-collector/reset-on-each-request');
    }

    private function assertNothingSurvivesBetweenRequests(string $path): void
    {
        /** @var list<array{statusCode: int, survived: mixed}> $observations */
        $observations = [];

        // Assertions deliberately run after the server scenario, not inside it: an assertion failure
        // thrown from within the coroutine escapes the coroutine pool and takes the whole PHP process
        // down, which PHPUnit can then only report as "test ended unexpectedly".
        // phpcs:ignore SlevomatCodingStandard.PHP.DisallowReference
        $this->withServer(static function (HttpClient $client) use ($path, &$observations): void {
            for ($i = 0; $i < self::REQUESTS; ++$i) {
                if ($i > 0) {
                    // The instance is only returned to the free pool once the request coroutine has
                    // actually finished, which happens after the response reached the client. Without
                    // this pause the next request overtakes the release and gets a brand new instance,
                    // so no instance is ever recycled and the test cannot observe carried-over state.
                    usleep(self::RELEASE_GRACE_MICROSECONDS);
                }

                $response = $client->send($path)['response'];
                $body = $response['body'];

                $observations[] = [
                    'statusCode' => $response['statusCode'],
                    'survived' => is_array($body) ? ($body['count_before_this_request'] ?? null) : null,
                ];
            }
        });

        self::assertCount(self::REQUESTS, $observations);

        foreach ($observations as $i => $observation) {
            self::assertSame(200, $observation['statusCode'], sprintf('request #%d to %s failed', $i, $path));

            self::assertSame(
                0,
                $observation['survived'],
                sprintf(
                    'request #%d to %s started with %s entries left over from earlier requests - the pooled '
                        . 'instance is not being reset before it is handed to the next coroutine.',
                    $i,
                    $path,
                    var_export($observation['survived'], true),
                ),
            );
        }
    }

    /**
     * @param callable(HttpClient): void $scenario
     */
    private function withServer(callable $scenario): void
    {
        $envs = ['APP_ENV' => self::ENV, 'WORKER_COUNT' => '1'];

        // setUp() throws the compiled cache away before every test, and this class is the first in the
        // suite to build the coroutines_profiler container - so without this the server would compile it
        // while booting. Compiling it up front keeps the boot itself short.
        $clearCache = $this->createConsoleProcess(['cache:clear'], $envs);
        $clearCache->setTimeout(self::PROCESS_TIMEOUT_SECONDS);
        $clearCache->disableOutput();
        $clearCache->run();

        $this->assertProcessSucceeded($clearCache);

        // swoole:server:start returns only once the server is actually listening, unlike
        // swoole:server:run which stays in the foreground and leaves the client racing the boot - a race
        // the client loses on a slow machine, where booting takes longer than the few seconds it waits.
        $serverStart = $this->createConsoleProcess([
            'swoole:server:start',
            '--host=localhost',
            '--port=9999',
        ], $envs);

        $serverStart->setTimeout(self::PROCESS_TIMEOUT_SECONDS);
        $serverStart->run();

        $this->assertProcessSucceeded($serverStart);

        $connected = false;

        // phpcs:ignore SlevomatCodingStandard.PHP.DisallowReference
        $this->runAsCoroutineAndWait(function () use ($scenario, $envs, &$connected): void {
            $this->deferServerStop([], $envs);

            $client = HttpClient::fromDomain('localhost', 9999, false);
            $connected = $client->connect(3, 1, true);

            if (!$connected) {
                return;
            }

            $scenario($client);
        });

        self::assertTrue($connected, 'the server was started but never answered.');
    }
}

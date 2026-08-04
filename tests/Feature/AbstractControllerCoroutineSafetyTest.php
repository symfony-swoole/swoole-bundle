<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Feature;

use Override;
use SwooleBundle\SwooleBundle\Client\HttpClient;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Test\ServerTestCase;

/**
 * Guards that serving a request does not write to the controller that served it.
 *
 * A controller registered as a service is shared: ContainerControllerResolver hands out the very same
 * instance for the whole life of the worker. FrameworkBundle's resolver nevertheless writes to its
 * $container on every single request - twice, once to take the previous value out of it and once to put
 * that value straight back - only to check that a container was injected at all:
 *
 * ```php
 * if (null === $previousContainer = $controller->setContainer($this->container)) {
 *     throw new LogicException(...);
 * }
 *
 * $controller->setContainer($previousContainer);
 * ```
 *
 * Nothing is configured by that and nothing ends up changed, but it is still per-request mutation of an
 * object shared by every coroutine in the worker - exactly the kind of shared write this bundle exists to
 * keep out of a server. NonMutatingControllerResolver keeps the check and drops the writes.
 *
 * The controller counts the writes to itself and reports the total, so the difference is plain: with
 * Symfony's resolver the count climbs by two per request, with this bundle's it stays where the service's
 * own injection left it.
 *
 * @see \SwooleBundle\SwooleBundle\Bridge\Symfony\HttpKernel\Controller\NonMutatingControllerResolver
 */
final class AbstractControllerCoroutineSafetyTest extends ServerTestCase
{
    private const string ENV = 'coroutines';

    private const string PATH = '/abstract-based-controller';

    private const int REQUESTS = 4;

    /**
     * The one write every response is expected to report: the container injected into the service when
     * it was built.
     */
    private const int WRITES_ON_BUILD = 1;

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

    public function testServingARequestDoesNotWriteToTheSharedController(): void
    {
        /** @var list<array{statusCode: int, body: mixed}> $responses */
        $responses = [];

        // Assertions run after the server scenario: an assertion failure thrown from inside the
        // coroutine escapes the coroutine pool and takes the whole PHP process down with it, which
        // PHPUnit can then only report as "test ended unexpectedly".
        // phpcs:ignore SlevomatCodingStandard.PHP.DisallowReference
        $this->withServer(static function (HttpClient $client) use (&$responses): void {
            for ($i = 0; $i < self::REQUESTS; ++$i) {
                $response = $client->send(self::PATH)['response'];

                $responses[] = ['statusCode' => $response['statusCode'], 'body' => $response['body']];
            }
        });

        self::assertCount(self::REQUESTS, $responses);

        $writeCounts = [];

        foreach ($responses as $i => $response) {
            $body = $response['body'];

            self::assertSame(
                200,
                $response['statusCode'],
                sprintf("request #%d was not served.\nresponse: %s", $i, json_encode($body)),
            );

            self::assertIsArray($body);
            self::assertArrayHasKey('containerWrites', $body);

            $writeCounts[] = $body['containerWrites'];
        }

        self::assertSame(
            array_fill(0, self::REQUESTS, self::WRITES_ON_BUILD),
            $writeCounts,
            'The controller is written to once, when its service is built. Serving requests must add '
                . 'nothing to that - a count climbing by two per request is Symfony\'s resolver still in '
                . 'place.',
        );
    }

    /**
     * @param callable(HttpClient): void $scenario
     */
    private function withServer(callable $scenario): void
    {
        $envs = ['APP_ENV' => self::ENV, 'WORKER_COUNT' => '1'];

        // setUp() throws the compiled cache away before the test, so compile the container here instead
        // of leaving it for the server to do while booting
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

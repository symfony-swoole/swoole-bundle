<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Feature;

use Override;
use SwooleBundle\SwooleBundle\Client\HttpClient;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\RequestHandler\ThrowingRequestHandler;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Test\ServerTestCase;
use Throwable;

/**
 * Guards that a second throwable is still rendered by the pooled Symfony ErrorHandler.
 *
 * ErrorHandler::handleException() parks `[$this, 'renderException']` in $exceptionHandler and never
 * takes it back out. renderException() is private, so that array is only callable from inside
 * ErrorHandler itself. Once the pooled instance is handed to the next coroutine, ErrorResponder calls
 * setExceptionHandler() on it through the generated proxy - whose override inherits the `?callable`
 * return type, which PHP validates in the proxy subclass' scope, where a private method of the parent
 * is not callable. The leftover therefore blows up as
 * "Return value must be of type ?callable, array returned".
 *
 * That is a fatal, not an exception: it kills the worker instead of producing a response, and it hides
 * whatever actually went wrong behind an error about the error handler. The first errored request
 * always looks fine, every one after it does not - so a single request cannot catch this and the test
 * has to send several.
 *
 * @see \SwooleBundle\SwooleBundle\Bridge\Symfony\ErrorHandler\ErrorHandlerResetter
 */
final class PooledErrorHandlerResetTest extends ServerTestCase
{
    private const string ENV = 'coroutines';

    private const int REQUESTS = 4;

    /**
     * The ErrorHandler instance only goes back to the free pool once the request coroutine has finished,
     * which happens after the response reached the client. Without this pause the next request overtakes
     * the release and is handed a brand new instance, which has no leftover exception handler on it -
     * so nothing is ever recycled and the bug cannot show up.
     */
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

    public function testEveryThrowableEscapingTheKernelIsRenderedNotJustTheFirst(): void
    {
        /** @var list<array{statusCode: int|null, body: mixed}> $responses */
        $responses = [];

        // Assertions run after the server scenario: an assertion failure thrown from inside the
        // coroutine escapes the coroutine pool and takes the whole PHP process down with it, which
        // PHPUnit can then only report as "test ended unexpectedly".
        // phpcs:ignore SlevomatCodingStandard.PHP.DisallowReference
        $this->withServer(static function (HttpClient $client) use (&$responses): void {
            for ($i = 0; $i < self::REQUESTS; ++$i) {
                if ($i > 0) {
                    usleep(self::RELEASE_GRACE_MICROSECONDS);
                }

                try {
                    $response = $client->send(ThrowingRequestHandler::PATH)['response'];
                } catch (Throwable $clientFailure) {
                    // the worker died on this request instead of answering it, so there is no response
                    // to inspect - record the failure and let the assertions below report it
                    $responses[] = ['statusCode' => null, 'body' => $clientFailure->getMessage()];

                    break;
                }

                $responses[] = [
                    'statusCode' => $response['statusCode'],
                    'body' => $response['body'],
                ];
            }
        });

        foreach ($responses as $i => $response) {
            self::assertSame(
                500,
                $response['statusCode'],
                sprintf(
                    "request #%d produced no error response - the worker died handling it.\nclient reported: %s",
                    $i,
                    is_string($response['body']) ? $response['body'] : '',
                ),
            );

            $body = is_string($response['body']) ? $response['body'] : json_encode($response['body']);

            // the point of the test: the response has to describe the throwable that was actually
            // raised, not a TypeError coming out of the error handler itself
            self::assertIsString($body);
            self::assertStringContainsString(
                ThrowingRequestHandler::MESSAGE,
                $body,
                sprintf('request #%d did not report the original throwable.', $i),
            );
            self::assertStringNotContainsString(
                'setExceptionHandler',
                $body,
                sprintf(
                    'request #%d failed inside the error handler itself - the pooled ErrorHandler still '
                        . 'carried the exception handler left behind by an earlier request.',
                    $i,
                ),
            );
        }

        self::assertCount(self::REQUESTS, $responses);
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

            $scenario($client);
        });

        self::assertTrue($connected, 'the server was started but never answered.');
    }
}

<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Feature;

use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use Swoole\Coroutine;
use SwooleBundle\SwooleBundle\Client\Http;
use SwooleBundle\SwooleBundle\Client\HttpClient;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Test\ServerTestCase;
use Throwable;

/**
 * Two requests of one session at once, in one worker: a slow one that only reads the session, and a fast one that
 * stores something in it while the slow one is still running.
 *
 * Nothing locks a session between its read and its write, so the slow one, finishing last, used to write back the
 * session it had read and take away what the fast one stored. A form's CSRF token went that way: stored as the page
 * with the form rendered, gone by the time the form was posted, and the form "expired".
 */
final class SessionOverlappingRequestsTest extends ServerTestCase
{
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->deleteVarDirectory();
    }

    #[DataProvider('coroutineSessionEnvironments')]
    public function testWhatAFastRequestStoredSurvivesASlowOneThatChangedNothing(
        string $environment,
        bool $inTheDatabase,
    ): void {
        $envs = [
            'APP_ENV' => $environment,
            'APP_DEBUG' => '0',
            'COOKIE_LIFETIME' => 60,
        ];

        $commands = [['cache:clear']];

        if ($inTheDatabase) {
            $commands[] = ['doctrine:schema:drop', '--full-database', '--force'];
            $commands[] = ['doctrine:migrations:migrate', '--no-interaction'];
        }

        $commands[] = [
            'swoole:server:start',
            '--host=localhost',
            sprintf('--port=%d', self::port()),
            // Both environments turn the API server on, which listens on 9200 unless told otherwise - the same
            // port for every parallel worker. One of this worker's own keeps them apart.
            sprintf('--api-port=%d', self::port(1)),
        ];

        foreach ($commands as $command) {
            $process = $this->createConsoleProcess($command, $envs);
            $process->setTimeout(10);
            $process->disableOutput();
            $process->run();

            $this->assertProcessSucceeded($process);
        }

        $this->runAsCoroutineAndWait(function () use ($envs): void {
            $this->deferServerStop([], $envs);

            $client = HttpClient::fromDomain('localhost', self::port(), false);
            $this->assertTrue($client->connect(self::connectTimeout(), 1, true));

            $first = $client->send('/session')['response'];
            $this->assertSame(200, $first['statusCode']);
            $this->assertArrayHasKey('SWOOLESSID', $first['cookies']);
            $headers = ['Cookie' => 'SWOOLESSID=' . $first['cookies']['SWOOLESSID']];

            $finished = [];
            $failures = [];
            $wg = $this->getSwoole()->waitGroup();
            $wg->add();

            // phpcs:ignore SlevomatCodingStandard.PHP.DisallowReference.DisallowedInheritingVariableByReference
            go(function () use ($wg, $headers, &$finished, &$failures): void {
                try {
                    $slow = HttpClient::fromDomain('localhost', self::port(), false);
                    $this->assertTrue($slow->connect(self::connectTimeout(), 1, true));
                    $response = $slow->send('/session/unchanged-slowly', Http::METHOD_GET, $headers, null, 5);
                    $this->assertSame(200, $response['response']['statusCode']);
                    $finished[] = 'slow';
                } catch (Throwable $throwable) {
                    $failures[] = $throwable->getMessage();
                } finally {
                    $wg->done();
                }
            });

            // The slow one has read the session by now, and is a second from its end. Whole seconds: OpenSwoole's
            // sleep() takes nothing else.
            Coroutine::sleep(1);

            $fast = HttpClient::fromDomain('localhost', self::port(), false);
            $this->assertTrue($fast->connect(self::connectTimeout(), 1, true));
            $stored = $fast->send('/session/remember/the-token', Http::METHOD_GET, $headers)['response'];
            $this->assertSame(200, $stored['statusCode']);
            $finished[] = 'fast';

            self::assertTrue($wg->wait(self::connectTimeout(10)), 'the slow request did not finish.');
            self::assertSame([], $failures, 'the slow request failed');
            // Otherwise they did not overlap, and the test would pass whatever the slow one wrote.
            self::assertSame(['fast', 'slow'], $finished, 'the requests did not overlap');

            $read = $client->send('/session/remembered', Http::METHOD_GET, $headers)['response'];
            self::assertSame(200, $read['statusCode']);
            self::assertSame(['remembered' => 'the-token'], $read['body']);
        });
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function coroutineSessionEnvironments(): iterable
    {
        yield 'in a Swoole table' => ['coroutines_session', false];
        yield 'in the database, through PdoSessionHandler' => ['coroutines_session_pdo', true];
    }
}

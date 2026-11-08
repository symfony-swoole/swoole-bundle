<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Feature;

use ArrayObject;
use Override;
use PDO;
use SwooleBundle\SwooleBundle\Client\HttpClient;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Test\ServerTestCase;
use SwooleBundle\SwooleBundle\Tests\Helper\TestToken;

/**
 * What a burst of requests leaves open on the database once it is over.
 *
 * Every coroutine that uses the database is given a pooled connection of its own, and gives it back as it ends. A
 * pool without an idle limit keeps every one it is given back, so a burst leaves as many sessions open on the
 * database as it had requests at once, for as long as the worker lives. With `max_idle_service_instances` the pool
 * keeps that many, and closes the rest as they are given back (ConnectionDisposer).
 *
 * Each request answers with the id of the database session it ran on, so the burst is shown to have opened a
 * connection for each request. What is left once it is over is counted the way the database sees it: the sessions of
 * this worker's database in MySQL's processlist, the test's own left out. The test connects only then - on OpenSwoole,
 * a connection the test process holds open while the burst is sent gets the server's connections reset.
 *
 * @see \SwooleBundle\SwooleBundle\Bridge\Symfony\Container\ServicePool\BaseServicePool
 * @see \SwooleBundle\SwooleBundle\Bridge\Doctrine\DBAL\ConnectionDisposer
 */
final class ServicePoolIdleLimitTest extends ServerTestCase
{
    private const string ENVIRONMENT = 'coroutines_idle_limit';

    /**
     * The fixture environment's `max_idle_service_instances`.
     */
    private const int IDLE_LIMIT = 2;

    private const int CONCURRENT_REQUESTS = 8;

    /**
     * How long the connections given back are waited for: a coroutine gives its connection back as it ends, a moment
     * after its response.
     */
    private const float SETTLE_SECONDS = 5.0;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->deleteVarDirectory();
    }

    public function testTheConnectionsOfABurstBeyondTheIdleLimitAreClosedOnceItIsOver(): void
    {
        $envs = ['APP_ENV' => self::resolveEnvironment(self::ENVIRONMENT)];
        // a cold worker builds its container as the first request comes in, which OpenSwoole answers with a reset
        $clearCache = $this->createConsoleProcess(['cache:clear'], $envs);
        $clearCache->setTimeout(30 * TestToken::timeoutFactor());
        $clearCache->disableOutput();
        $clearCache->run();

        $this->assertProcessSucceeded($clearCache);

        $serverStart = $this->createConsoleProcess(
            ['swoole:server:start', '--host=localhost', sprintf('--port=%d', self::port())],
            $envs,
        );
        $serverStart->setTimeout(10 * TestToken::timeoutFactor());
        $serverStart->disableOutput();
        $serverStart->run();

        $this->assertProcessSucceeded($serverStart);

        $this->runAsCoroutineAndWait(function () use ($envs): void {
            $this->deferServerStop([], $envs);

            $initClient = HttpClient::fromDomain('localhost', self::port(), false);
            self::assertTrue($initClient->connect(self::connectTimeout(), 1, true));

            /** @var ArrayObject<int, array{statusCode: int, body: mixed}> $responses */
            $responses = new ArrayObject();
            $burst = $this->getSwoole()->waitGroup();

            for ($i = 0; $i < self::CONCURRENT_REQUESTS; ++$i) {
                $burst->add();
                // straight to the request: the server is up, and a HEAD probe sent while the worker's other
                // requests sleep is answered by OpenSwoole with a reset
                go(static function () use ($burst, $responses): void {
                    $client = HttpClient::fromDomain('localhost', self::port(), false);
                    $responses->append($client->send('/doctrine-hold')['response']);
                    $burst->done();
                });
            }

            $burst->wait(10);
            // the test's own connection is a temporary, let go of here, inside the coroutine it was opened in
            $leftOpen = self::sessionsOnceSettled(self::database());

            self::assertSame(
                array_fill(0, self::CONCURRENT_REQUESTS, 200),
                array_column($responses->getArrayCopy(), 'statusCode'),
            );
            self::assertCount(
                self::CONCURRENT_REQUESTS,
                array_unique(array_column($responses->getArrayCopy(), 'body')),
                'The requests did not each run on a session of their own, so nothing below proves anything.',
            );
            self::assertLessThanOrEqual(
                self::IDLE_LIMIT,
                $leftOpen,
                sprintf(
                    'The pool kept %d connections open on the database after the burst, past its idle limit of %d.',
                    $leftOpen,
                    self::IDLE_LIMIT,
                ),
            );
        });
    }

    /**
     * Connected inside the coroutine: with the runtime hooked, PDO's constructor is a coroutine-only API.
     */
    private static function database(): PDO
    {
        $host = getenv('DATABASE_HOST');

        return new PDO(
            sprintf(
                'mysql:host=%s;port=3306;dbname=%s',
                is_string($host) && $host !== '' ? $host : 'db',
                TestToken::databaseName(),
            ),
            'user',
            'pass',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
    }

    /**
     * The sessions on this worker's database but the test's own - the fixture user sees only its own sessions.
     */
    private static function sessions(PDO $database): int
    {
        $statement = $database->query(
            'SELECT COUNT(*) FROM information_schema.PROCESSLIST WHERE DB = DATABASE() AND ID <> CONNECTION_ID()',
        );

        return (int) $statement->fetchColumn();
    }

    /**
     * The sessions left once the pool has had the time to close what it does not keep - or once that time is up.
     */
    private static function sessionsOnceSettled(PDO $database): int
    {
        $deadline = microtime(true) + self::SETTLE_SECONDS * TestToken::timeoutFactor();

        do {
            $sessions = self::sessions($database);

            if ($sessions <= self::IDLE_LIMIT) {
                return $sessions;
            }

            usleep(100_000);
        } while (microtime(true) < $deadline);

        return $sessions;
    }
}

<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Feature;

use Doctrine\DBAL\Connection;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use SwooleBundle\SwooleBundle\Client\HttpClient;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Test\ServerTestCase;

final class SessionHandlerInterfaceStorageTest extends ServerTestCase
{
    private const string SESSION_TABLE = 'symfony_session';

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->deleteVarDirectory();
    }

    public function testSessionDataIsPersistedThroughPdoHandler(): void
    {
        $cookieLifetime = 5;
        $envs = [
            'APP_ENV' => 'session_pdo',
            'COOKIE_LIFETIME' => $cookieLifetime,
        ];

        $clearCache = $this->createConsoleProcess(['cache:clear'], $envs);
        $clearCache->setTimeout(5);
        $clearCache->disableOutput();
        $clearCache->run();

        $this->assertProcessSucceeded($clearCache);

        $dropSchema = $this->createConsoleProcess(
            [
                'doctrine:schema:drop',
                '--full-database',
                '--force',
            ],
            $envs
        );
        $dropSchema->setTimeout(5);
        $dropSchema->disableOutput();
        $dropSchema->run();

        $this->assertProcessSucceeded($dropSchema);

        $migrations = $this->createConsoleProcess(
            [
                'doctrine:migrations:migrate',
                '--no-interaction',
            ],
            $envs
        );
        $migrations->setTimeout(5);
        $migrations->disableOutput();
        $migrations->run();

        $this->assertProcessSucceeded($migrations);

        $serverStart = $this->createConsoleProcess([
            'swoole:server:start',
            '--host=localhost',
            sprintf('--port=%d', self::port()),
        ], $envs);

        $serverStart->setTimeout(5);
        $serverStart->disableOutput();
        $serverStart->run();

        $this->assertProcessSucceeded($serverStart);

        $this->runAsCoroutineAndWait(function () use ($envs): void {
            $this->deferServerStop([], $envs);

            $client = HttpClient::fromDomain('localhost', self::port(), false);
            $this->assertTrue($client->connect(self::connectTimeout(), 1, true));

            $response1 = $client->send('/session/1')['response'];
            $this->assertSame(200, $response1['statusCode']);
            $this->assertArrayHasKey('set-cookie', $response1['headers']);
            $this->assertArrayHasKey('SWOOLESSID', $response1['cookies']);
            $sessionId1 = $response1['cookies']['SWOOLESSID'];
            $body1 = $response1['body'];

            $response2 = $client->send('/session/2')['response'];
            $this->assertArrayHasKey('SWOOLESSID', $response2['cookies']);
            $sessionId2 = $response2['cookies']['SWOOLESSID'];
            $body2 = $response2['body'];

            $this->assertSame($sessionId1, $sessionId2);
            $this->assertSame($body1, $body2);

            $row = $this->fetchSessionRow($sessionId1);
            $this->assertNotNull($row);
            $this->assertNotEmpty($row['data']);
            $this->assertStringContainsString('luckyNumber', $row['data']);
        });
    }

    /**
     * A worker keeps the handler's PDO connection for longer than a database keeps an idle one: MySQL closes it
     * after its `wait_timeout`, and a restart closes them all. The session has to be there for the next request
     * all the same - looked after as the connection is handed to a coroutine, or, without coroutines, as the
     * resetter bundle starts the request.
     */
    #[DataProvider('pdoSessionEnvironments')]
    public function testSessionSurvivesTheDatabaseClosingTheHandlersConnection(string $environment): void
    {
        $envs = [
            'APP_ENV' => $environment,
            'COOKIE_LIFETIME' => 60,
        ];

        foreach (
            [
                ['cache:clear'],
                ['doctrine:schema:drop', '--full-database', '--force'],
                ['doctrine:migrations:migrate', '--no-interaction'],
                ['swoole:server:start', '--host=localhost', sprintf('--port=%d', self::port())],
            ] as $command
        ) {
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

            $before = $client->send('/session/1')['response'];
            $this->assertSame(200, $before['statusCode']);
            $sessionId = $before['cookies']['SWOOLESSID'];

            // Every connection the server holds to this test's database, the session handler's among them.
            $this->assertGreaterThan(0, $this->closeTheServersConnections());

            $after = $client->send('/session/2')['response'];

            $this->assertSame(200, $after['statusCode']);
            $this->assertSame($sessionId, $after['cookies']['SWOOLESSID']);
            $this->assertSame($before['body'], $after['body']);
            $this->assertNotNull($this->fetchSessionRow($sessionId));
        });
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function pdoSessionEnvironments(): iterable
    {
        yield 'without coroutines, looked after by the resetter bundle' => ['session_pdo'];
        yield 'with coroutines, looked after as it is handed out' => ['coroutines_session_pdo'];
    }

    /**
     * Has the server close every connection to this test's database but the one that asks, as it does by itself
     * to one idle for its `wait_timeout`. A parallel worker has a database of its own, and is left alone.
     */
    private function closeTheServersConnections(): int
    {
        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $ids = $connection->fetchFirstColumn(
            'SELECT id FROM information_schema.processlist WHERE db = DATABASE() AND id <> CONNECTION_ID()',
        );
        foreach ($ids as $id) {
            $connection->executeStatement('KILL ' . (int) $id);
        }

        return count($ids);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchSessionRow(string $sessionId): ?array
    {
        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $stmt = $connection->prepare('SELECT * FROM ' . self::SESSION_TABLE . ' WHERE id = :id');
        $stmt->bindValue(':id', $sessionId);
        $result = $stmt->executeQuery();
        $row = $result->fetchAssociative();

        return $row === false ? null : $row;
    }
}

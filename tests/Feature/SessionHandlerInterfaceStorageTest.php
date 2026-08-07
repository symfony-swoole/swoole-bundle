<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Feature;

use Doctrine\DBAL\Connection;
use Override;
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
            '--port=9999',
        ], $envs);

        $serverStart->setTimeout(5);
        $serverStart->disableOutput();
        $serverStart->run();

        $this->assertProcessSucceeded($serverStart);

        $this->runAsCoroutineAndWait(function () use ($envs): void {
            $this->deferServerStop([], $envs);

            $client = HttpClient::fromDomain('localhost', 9999, false);
            $this->assertTrue($client->connect(3, 1, true));

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

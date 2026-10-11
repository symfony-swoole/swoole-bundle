<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Symfony\HttpFoundation\Session\PdoSession;

use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use SwooleBundle\SwooleBundle\Bridge\Symfony\HttpFoundation\Session\PdoSession\PdoConnectionFactory;
use SwooleBundle\SwooleBundle\Bridge\Symfony\HttpFoundation\Session\PdoSession\PingingPDOAliveKeeper;

/**
 * A worker keeps the session handler's connection for longer than a database keeps an idle one: MySQL closes it
 * after its `wait_timeout`, and a restart closes them all.
 */
final class PingingPDOAliveKeeperTest extends TestCase
{
    public function testAConnectionTheServerClosedIsMadeAgainInPlace(): void
    {
        $url = 'mysql://user:pass@' . $this->databaseHost() . ':3306/db';
        $pdo = PdoConnectionFactory::newInstanceFromDsnOrUrl($url);
        $logger = $this->logger();
        $aliveKeeper = new PingingPDOAliveKeeper(PdoConnectionFactory::constructorArgumentsFromDsnOrUrl($url), $logger);
        $first = $this->connectionId($pdo);

        // The server closes it, as it does by itself to one idle for its `wait_timeout`.
        try {
            $pdo->exec('KILL ' . $first);
        } catch (PDOException) {
            // Its answer is that the connection the statement came on is gone.
        }

        $aliveKeeper->keepAlive($pdo, 'session');

        // The same object, connected again - and as a session handler needs it: its statements throw.
        $this->assertNotSame($first, $this->connectionId($pdo));
        $this->assertSame(PDO::ERRMODE_EXCEPTION, $pdo->getAttribute(PDO::ATTR_ERRMODE));
        $this->assertSame(["Exceptional reconnect for PDO connection 'session'"], $logger->messages());
    }

    public function testAConnectionThatAnswersIsLeftAsItIs(): void
    {
        $url = 'mysql://user:pass@' . $this->databaseHost() . ':3306/db';
        $pdo = PdoConnectionFactory::newInstanceFromDsnOrUrl($url);
        $logger = $this->logger();
        $first = $this->connectionId($pdo);

        (new PingingPDOAliveKeeper(PdoConnectionFactory::constructorArgumentsFromDsnOrUrl($url), $logger))
            ->keepAlive($pdo, 'session');

        $this->assertSame($first, $this->connectionId($pdo));
        $this->assertSame([], $logger->messages());
    }

    public function testNothingIsAskedInsideATransaction(): void
    {
        // What it would be constructed with leads nowhere: asked, it could only fail.
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('CREATE TABLE kept (id INTEGER)');
        $pdo->beginTransaction();
        $pdo->exec('INSERT INTO kept VALUES (1)');

        (new PingingPDOAliveKeeper(['sqlite:/nowhere/at/all.db']))->keepAlive($pdo, 'session');

        // A new connection would be another one, outside the transaction: this is still the one, with its row.
        $this->assertTrue($pdo->inTransaction());
        $this->assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM kept')->fetchColumn());
    }

    public function testItWorksWithoutALogger(): void
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('CREATE TABLE kept (id INTEGER)');

        (new PingingPDOAliveKeeper(['sqlite::memory:']))->keepAlive($pdo, 'session');

        $this->assertNotFalse($pdo->query('SELECT * FROM kept'));
    }

    private function connectionId(PDO $pdo): int
    {
        return (int) $pdo->query('SELECT CONNECTION_ID()')->fetchColumn();
    }

    private function databaseHost(): string
    {
        return getenv('DATABASE_HOST') ?: 'db';
    }

    private function logger(): RecordingLogger
    {
        return new RecordingLogger();
    }
}
